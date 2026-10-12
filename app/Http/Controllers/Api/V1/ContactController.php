<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\Block;
use App\Models\ContactMessage;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * POST /api/v1/contact — a Contact form block's message (Phase 11A). The block is named by
 * its id; its recipient is read on the server, never sent by the browser.
 */
class ContactController extends Controller
{
    /** Forms sent faster than this were filled by a program. */
    public const MIN_SECONDS = 3;

    public function __invoke(Request $request, SettingsService $settings): JsonResponse
    {
        // Spam traps: a hidden field people leave empty, and a minimum fill time. A caught
        // robot gets a normal-looking answer, so it learns nothing.
        if (filled($request->input('website')) || (int) $request->input('elapsed', 0) < self::MIN_SECONDS * 1000) {
            return response()->json(['message' => 'Thank you.'], 202);
        }

        $key = 'contact:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($key.':day', 20)) {
            throw ValidationException::withMessages(['message' => __('Too many messages from your connection. Please try again later.')]);
        }

        $block = $this->block((string) $request->input('block'));
        $content = (array) ($block->content ?? []);
        $needsConsent = trim((string) ($content['consent_text'] ?? '')) !== '';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => $needsConsent ? ['accepted'] : ['nullable'],
            'page' => ['nullable', 'string', 'max:2048'],
        ], ['consent.accepted' => __('Please tick the box to agree.')]);

        RateLimiter::hit($key, 60);
        RateLimiter::hit($key.':day', 86400);

        $recipient = (string) ($content['recipient'] ?? '') ?: (string) $settings->get('site', 'contact_email');
        $message = ContactMessage::query()->create([
            'name' => HtmlSanitizer::plain($data['name']),
            'email' => $data['email'],
            'subject' => isset($data['subject']) ? HtmlSanitizer::plain($data['subject']) : null,
            'message' => trim(strip_tags($data['message'])),
            'recipient' => $recipient,
            'page_url' => isset($data['page']) && str_starts_with($data['page'], '/') ? mb_substr($data['page'], 0, 2048) : null,
            'block_uuid' => $block?->uuid,
            'consent' => $needsConsent,
        ]);

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($recipient)->queue(new ContactMessageMail($message, (string) $settings->get('site', 'name')));
                $message->forceFill(['mailed' => true])->save();
            } catch (Throwable $e) {
                report($e); // the message is kept in the admin either way
            }
        }

        return response()->json(['message' => (string) ($content['thank_you'] ?? __('Thank you! We have received your message.'))], 201);
    }

    private function block(string $uuid): ?Block
    {
        if (! preg_match('/^[0-9a-z]{26}$/', $uuid)) {
            return null;
        }

        return Block::query()->where('uuid', $uuid)->whereHas('type', fn ($q) => $q->where('slug', 'contact-form'))->first();
    }
}
