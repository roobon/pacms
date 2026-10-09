<?php

namespace App\Services\Testimonials;

use App\Enums\RevisionKind;
use App\Enums\TestimonialAction;
use App\Enums\TestimonialStatus;
use App\Models\Media;
use App\Models\Testimonial;
use App\Models\TestimonialModerationLog;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentReferenceService;
use App\Services\Media\MediaService;
use App\Services\Revisions\RevisionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Submission and moderation of testimonials (CMS-ARCHITECTURE.md §18). Every step is
 * written to the moderation log; the original submission is kept as the first revision,
 * and every staff edit records another.
 */
class TestimonialModerationService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly RevisionService $revisions,
        private readonly ContentReferenceService $references,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
    ) {}

    /**
     * A registered user's submission: straight into the moderation queue (Pending). The photo
     * is stored as a private library file until the testimonial is published.
     *
     * @param  array<string, mixed>  $data  validated: name, organization, designation, body, program_id, project_id
     */
    public function submit(User $user, array $data, ?UploadedFile $photo, ?string $ip): Testimonial
    {
        $media = $photo !== null ? $this->media->store($photo, $user, ['alt' => $data['name']], private: true) : null;

        return DB::transaction(function () use ($user, $data, $media, $ip) {
            $testimonial = new Testimonial;
            $testimonial->forceFill([
                'user_id' => $user->id,
                'name' => $data['name'],
                'organization' => $data['organization'] ?? null,
                'designation' => $data['designation'] ?? null,
                'body' => $data['body'],
                'program_id' => $data['program_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'photo_media_id' => $media?->id,
                'testimonial_date' => now()->toDateString(),
                'status' => TestimonialStatus::Pending,
                'consent_given_at' => now(),
                'consent_version' => (string) config('pacms.testimonials.consent_version'),
            ]);
            $testimonial->setSubmittedIp($ip);
            $testimonial->save();

            $this->syncReferences($testimonial);
            $this->revisions->record($testimonial, RevisionKind::Manual, $user, 'Original submission');
            $this->log($testimonial, null, TestimonialStatus::Pending, $user, null);
            $this->logger->log('testimonial.submitted', $testimonial, [], $user, $testimonial->label());

            return $testimonial;
        });
    }

    /**
     * A testimonial entered by staff (e.g. from a letter or an interview). Starts as a draft.
     *
     * @param  array<string, mixed>  $data  validated Testimonial::EDITABLE fields
     */
    public function create(User $user, array $data): Testimonial
    {
        $this->authorize($user, 'testimonials.moderate');

        return DB::transaction(function () use ($user, $data) {
            $testimonial = new Testimonial;
            $testimonial->forceFill(array_intersect_key($data, array_flip(Testimonial::EDITABLE)));
            $testimonial->created_by = $testimonial->updated_by = $user->id;
            $testimonial->save();

            $this->syncReferences($testimonial);
            $this->revisions->record($testimonial, RevisionKind::Manual, $user, 'Created');
            $this->log($testimonial, null, TestimonialStatus::Draft, $user, null);
            $this->logger->log('testimonial.created', $testimonial, [], $user, $testimonial->label());

            return $testimonial;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function update(User $user, Testimonial $testimonial, array $data, int $lockVersion): Testimonial
    {
        $this->authorize($user, 'testimonials.moderate');
        // Changing a live testimonial changes the website.
        if ($testimonial->status === TestimonialStatus::Published) {
            $this->authorize($user, 'testimonials.publish');
        }

        return DB::transaction(function () use ($user, $testimonial, $data, $lockVersion) {
            $testimonial = Testimonial::query()->lockForUpdate()->findOrFail($testimonial->id);
            if ($testimonial->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lock_version' => __('This was changed by someone else while you were editing. Reload it to see the latest version, then apply your changes again.'),
                ]);
            }

            $before = $testimonial->toSnapshot();
            $testimonial->forceFill(array_intersect_key($data, array_flip(Testimonial::EDITABLE)));
            if (! $testimonial->isDirty()) {
                return $testimonial;
            }
            $testimonial->updated_by = $user->id;
            $testimonial->lock_version++;
            $testimonial->save();

            if ($testimonial->status === TestimonialStatus::Published) {
                $this->makePhotoPublic($testimonial, $user);
            }
            $this->syncReferences($testimonial);
            $this->revisions->record($testimonial, RevisionKind::Manual, $user, $this->revisions->summarize($before, $testimonial->toSnapshot()));
            $this->log($testimonial, $testimonial->status, $testimonial->status, $user, 'Edited');
            $this->logger->log('testimonial.updated', $testimonial, [], $user, $testimonial->label());
            $this->changed();

            return $testimonial;
        });
    }

    /**
     * Steps the user may take now.
     *
     * @return list<TestimonialAction>
     */
    public function availableActions(Testimonial $testimonial, User $user): array
    {
        return array_values(array_filter(
            TestimonialAction::cases(),
            fn (TestimonialAction $action) => in_array($testimonial->status, $action->allowedFrom(), true) && $user->can($action->permission()),
        ));
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function transition(Testimonial $testimonial, TestimonialAction $action, User $user, ?string $note = null): Testimonial
    {
        $this->authorize($user, $action->permission());

        if (! in_array($testimonial->status, $action->allowedFrom(), true)) {
            throw ValidationException::withMessages(['action' => __('":action" is not possible while the testimonial is :status.', [
                'action' => $action->label(), 'status' => strtolower($testimonial->status->label()),
            ])]);
        }

        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null;
        if ($action === TestimonialAction::Reject && $note === null) {
            throw ValidationException::withMessages(['note' => __('Give a reason for rejecting it (internal; the submitter does not see it unless the site is set to show it).')]);
        }

        $from = $testimonial->status;

        DB::transaction(function () use ($testimonial, $action, $user, $note, $from) {
            $testimonial->status = $action->to();
            $testimonial->updated_by = $user->id;
            if (in_array($action, [TestimonialAction::StartReview, TestimonialAction::Approve, TestimonialAction::Reject], true)) {
                $testimonial->reviewed_by = $user->id;
            }
            match ($action) {
                TestimonialAction::Reject => $testimonial->rejection_reason = $note,
                TestimonialAction::Reopen => $testimonial->rejection_reason = null,
                // The first publication date is kept when a testimonial is published again.
                TestimonialAction::Publish => $testimonial->published_at ??= now(),
                default => null,
            };
            $testimonial->save();

            if ($action === TestimonialAction::Publish) {
                $this->makePhotoPublic($testimonial, $user);
            }

            $this->log($testimonial, $from, $testimonial->status, $user, $note);
        });

        $this->logger->log('testimonial.'.$action->value, $testimonial, ['from' => $from->value, 'to' => $testimonial->status->value], $user, $testimonial->label());
        $this->changed();

        return $testimonial;
    }

    public function delete(User $user, Testimonial $testimonial): void
    {
        $this->authorize($user, 'testimonials.delete');

        DB::transaction(function () use ($user, $testimonial) {
            $testimonial->delete();
            $this->references->clear($testimonial);
            $this->logger->log('testimonial.deleted', $testimonial, [], $user, $testimonial->label());
        });
        $this->changed();
    }

    /**
     * Forget submitters' IP addresses after the retention period (SECURITY-ARCHITECTURE.md §9).
     */
    public function purgeIps(): int
    {
        return Testimonial::withTrashed()
            ->whereNotNull('submitted_ip')
            ->where('created_at', '<', now()->subDays((int) config('pacms.testimonials.ip_retention_days')))
            ->update(['submitted_ip' => null]);
    }

    /**
     * The photo, a library file, is used by the testimonial: it cannot be deleted while listed.
     */
    private function syncReferences(Testimonial $testimonial): void
    {
        $photo = $testimonial->photo_media_id ? Media::query()->find($testimonial->photo_media_id) : null;
        $this->references->sync($testimonial, $photo !== null ? [['target' => $photo, 'context' => 'testimonial_photo']] : []);
    }

    /**
     * Submitted photos stay private until the testimonial is published.
     */
    private function makePhotoPublic(Testimonial $testimonial, User $user): void
    {
        $photo = $testimonial->photo_media_id ? Media::query()->find($testimonial->photo_media_id) : null;
        if ($photo !== null && ! $photo->isPublic()) {
            $this->media->setVisibility($photo, false, $user);
        }
    }

    private function log(Testimonial $testimonial, ?TestimonialStatus $from, TestimonialStatus $to, ?User $actor, ?string $note): void
    {
        TestimonialModerationLog::query()->create([
            'testimonial_id' => $testimonial->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }

    private function authorize(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException(__('You are not allowed to do this with testimonials.'));
        }
    }

    private function changed(): void
    {
        // Pages show testimonials in blocks; their cached payloads depend on this group.
        $this->cache->bump('testimonials');
    }
}
