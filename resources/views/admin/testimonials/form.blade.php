@php
    use App\Enums\TestimonialAction;
    $editing = $item->exists;
    $readonly = ! $canEdit;
    $heading = $editing ? 'Testimonial from '.$item->name : 'New testimonial';
    $field = fn (string $name) => old($name, $item->getAttribute($name));
    $max = (int) config('pacms.testimonials.body_max');
@endphp
<x-admin.layout :title="$heading">
    <x-admin.page-header :title="$heading" :subtitle="$editing ? ($item->user_id ? 'Submitted online '.$item->created_at?->diffForHumans() : 'Entered by staff') : 'Starts as a draft. Approve and publish it to show it in Testimonials blocks.'">
        <x-slot:actions><a href="{{ route('admin.testimonials.index') }}" class="btn btn-link">All testimonials</a></x-slot:actions>
    </x-admin.page-header>

    @if ($editing && $readonly)
        <div class="alert alert-info pa-alert" role="status">
            <i class="bi bi-lock" aria-hidden="true"></i>
            {{ $item->status->value === 'published' ? 'This testimonial is live. Only people who may publish testimonials can change it.' : 'You can view this testimonial but not edit it.' }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="testimonial-form" method="POST" action="{{ $editing ? route('admin.testimonials.update', $item) : route('admin.testimonials.store') }}" novalidate>
                @csrf
                @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $item->lock_version }}"> @endif
                @error('lock_version')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror

                <fieldset @disabled($readonly)>
                    <section class="card pa-card mb-4" aria-labelledby="person-heading">
                        <div class="card-header"><h2 id="person-heading" class="h6 mb-0">Person</h2></div>
                        <div class="card-body">
                            <x-admin.field name="name" label="Name" :value="$item->name" required />
                            <div class="row">
                                <div class="col-md-6"><x-admin.field name="designation" label="Designation" :value="$item->designation" help="e.g. Teacher, Student, Director" /></div>
                                <div class="col-md-6"><x-admin.field name="organization" label="Organisation" :value="$item->organization" /></div>
                            </div>
                            @if ($canPickPhoto)
                                @if ($item->photo && ! $item->photo->isPublic())
                                    <p class="small mb-1"><img src="{{ route('admin.testimonials.photo', $item) }}" alt="" class="pa-testimonial-avatar me-2">Submitted photo, private until published.</p>
                                @endif
                                <x-admin.media-picker name="photo_media_id" label="Photo" :media="$item->photo" :disabled="$readonly"
                                    help="A submitted photo stays private until the testimonial is published." />
                            @else
                                <p class="form-label mb-1">Photo</p>
                                @if ($item->photo)
                                    <img src="{{ route('admin.testimonials.photo', $item) }}" alt="Photo of {{ $item->name }}" class="pa-testimonial-photo mb-3">
                                @else
                                    <p class="small text-body-secondary">No photo.</p>
                                @endif
                            @endif
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="text-heading">
                        <div class="card-header"><h2 id="text-heading" class="h6 mb-0">Testimonial</h2></div>
                        <div class="card-body">
                            <x-admin.field name="body" label="Testimonial" type="textarea" rows="7" :value="$item->body" required
                                :help="'Plain text. Submissions are limited to '.number_format($max).' characters.'" />
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="field-rating" class="form-label">Rating</label>
                                        <select id="field-rating" name="rating" class="form-select @error('rating') is-invalid @enderror">
                                            <option value="">No rating</option>
                                            @foreach (range(5, 1) as $stars)
                                                <option value="{{ $stars }}" @selected((string) $field('rating') === (string) $stars)>{{ $stars }} {{ Str::plural('star', $stars) }}</option>
                                            @endforeach
                                        </select>
                                        @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4"><x-admin.field name="testimonial_date" label="Date" type="date" :value="$item->testimonial_date?->format('Y-m-d')" /></div>
                                <div class="col-md-4"><x-admin.field name="citation" label="Source / citation" :value="$item->citation" help="e.g. Annual report 2025" /></div>
                            </div>
                            <x-admin.field name="website_url" label="Website or social profile" type="url" :value="$item->website_url" />
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="related-heading">
                        <div class="card-header"><h2 id="related-heading" class="h6 mb-0">Related to</h2></div>
                        <div class="card-body">
                            <div class="row">
                                @foreach (['program_id' => ['Program', $programs], 'project_id' => ['Project', $projects], 'event_id' => ['Event', $events]] as $name => [$label, $options])
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="field-{{ $name }}" class="form-label">{{ $label }}</label>
                                            <select id="field-{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                                <option value="">— None —</option>
                                                @foreach ($options as $optionId => $optionLabel)
                                                    <option value="{{ $optionId }}" @selected((string) $field($name) === (string) $optionId)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="small text-body-secondary mb-0">Testimonials blocks can show only those related to one program, project or event.</p>
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="display-heading">
                        <div class="card-header"><h2 id="display-heading" class="h6 mb-0">Display</h2></div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input type="hidden" name="featured" value="0">
                                <input class="form-check-input" type="checkbox" name="featured" value="1" id="field-featured" @checked($field('featured'))>
                                <label class="form-check-label" for="field-featured">Featured (blocks can show featured testimonials only)</label>
                            </div>
                            <x-admin.field name="position" label="Display order" type="number" :value="$item->position" help="Lower numbers come first in blocks sorted by display order." />
                        </div>
                    </section>
                </fieldset>
            </form>

            @if ($original)
                <section class="card pa-card mb-4" aria-labelledby="original-heading">
                    <div class="card-header"><h2 id="original-heading" class="h6 mb-0">Original submission</h2></div>
                    <div class="card-body">
                        <p class="small text-body-secondary">What {{ $original->snapshot['fields']['name'] ?? 'the person' }} sent, before it was edited.</p>
                        <blockquote class="pa-testimonial-original mb-0">{{ $original->snapshot['fields']['body'] ?? '' }}</blockquote>
                    </div>
                </section>
            @endif
        </div>

        <div class="col-xl-4">
            <aside class="pa-sticky" aria-label="Moderation">
                <section class="card pa-card mb-4" aria-labelledby="moderation-heading">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="moderation-heading" class="h6 mb-0">Moderation</h2>
                        @if ($editing)<span class="pa-badge pa-badge--{{ $item->status->badge() }}">{{ $item->status->label() }}</span>@endif
                    </div>
                    <div class="card-body">
                        @if ($editing)
                            <dl class="small mb-3 pa-meta">
                                @if ($item->user_id)
                                    <dt>Submitted by</dt>
                                    <dd>{{ $item->user?->name ?? 'Deleted account' }}@if ($item->user?->email) <span class="text-body-secondary">({{ $item->user->email }})</span>@endif</dd>
                                    <dt>Consent</dt>
                                    <dd>{{ $item->consent_given_at ? $item->consent_given_at->format('j M Y, H:i').' (version '.$item->consent_version.')' : '—' }}</dd>
                                @endif
                                @if ($item->reviewer)
                                    <dt>Reviewed by</dt>
                                    <dd>{{ $item->reviewer->name }}</dd>
                                @endif
                                @if ($item->rejection_reason)
                                    <dt>Rejection reason (internal)</dt>
                                    <dd>{{ $item->rejection_reason }}</dd>
                                @endif
                                <dt>On the website</dt>
                                <dd>{{ $item->isPublished() ? 'Shown in Testimonials blocks since '.$item->published_at?->format('j M Y') : 'Not shown' }}</dd>
                            </dl>
                        @endif

                        <div class="d-grid gap-2">
                            @if ($canEdit)
                                <button type="submit" form="testimonial-form" class="btn btn-primary">{{ ! $editing ? 'Save draft' : ($item->isPublished() ? 'Save and update live' : 'Save') }}</button>
                            @endif
                        </div>

                        @if ($editing && $actions !== [])
                            <hr>
                            <p class="small fw-semibold mb-2">Decision</p>
                            <div class="d-grid gap-2">
                                @foreach ($actions as $action)
                                    @continue($action === TestimonialAction::Reject)
                                    <form method="POST" action="{{ route('admin.testimonials.moderate', $item) }}"
                                          @if (in_array($action, [TestimonialAction::Unpublish, TestimonialAction::Archive], true)) data-confirm="{{ $action->label() }} this testimonial? It will no longer be shown on the website." @endif>
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $action->value }}">
                                        <button type="submit" @class([
                                            'btn w-100',
                                            'btn-success' => in_array($action, [TestimonialAction::Publish, TestimonialAction::Approve], true),
                                            'btn-outline-danger' => in_array($action, [TestimonialAction::Unpublish, TestimonialAction::Archive], true),
                                            'btn-outline-primary' => ! in_array($action, [TestimonialAction::Publish, TestimonialAction::Approve, TestimonialAction::Unpublish, TestimonialAction::Archive], true),
                                        ])>{{ $action->label() }}</button>
                                    </form>
                                @endforeach

                                @if (in_array(TestimonialAction::Reject, $actions, true))
                                    <form method="POST" action="{{ route('admin.testimonials.moderate', $item) }}" class="border rounded p-2">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <label for="reject-note" class="form-label small mb-1">Reason for rejecting (internal)</label>
                                        <textarea id="reject-note" name="note" rows="2" class="form-control form-control-sm mb-2 @error('note') is-invalid @enderror" maxlength="2000" required>{{ old('note') }}</textarea>
                                        @error('note')<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Reject</button>
                                    </form>
                                @endif
                            </div>
                            <p class="small text-body-secondary mt-2 mb-0">Approved testimonials are accepted but not shown until they are published.</p>
                        @endif
                        @error('action')<div class="alert alert-danger pa-alert mt-3 mb-0" role="alert">{{ $message }}</div>@enderror

                        @if ($editing)
                            @can('delete', $item)
                                <hr>
                                <form method="POST" action="{{ route('admin.testimonials.destroy', $item) }}" data-confirm="Delete the testimonial from {{ $item->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">Delete testimonial</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </section>

                @if ($editing)
                    <section class="card pa-card mb-4" aria-labelledby="preview-heading">
                        <div class="card-header"><h2 id="preview-heading" class="h6 mb-0">Preview</h2></div>
                        <div class="card-body">
                            <figure class="pa-testimonial-preview mb-0">
                                @if ($item->rating)<p class="mb-1" aria-label="{{ $item->rating }} out of 5 stars">{{ str_repeat('★', $item->rating) }}{{ str_repeat('☆', 5 - $item->rating) }}</p>@endif
                                <blockquote class="mb-2">“{{ $item->body }}”</blockquote>
                                <figcaption class="d-flex align-items-center gap-2">
                                    @if ($item->photo)<img src="{{ route('admin.testimonials.photo', $item) }}" alt="" class="pa-testimonial-avatar">@endif
                                    <span><strong>{{ $item->name }}</strong>@if ($item->designation || $item->organization)<br><span class="small text-body-secondary">{{ collect([$item->designation, $item->organization])->filter()->implode(', ') }}</span>@endif</span>
                                </figcaption>
                            </figure>
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="history-heading">
                        <div class="card-header"><h2 id="history-heading" class="h6 mb-0">Moderation history</h2></div>
                        <ol class="list-group list-group-flush small">
                            @foreach ($logs as $log)
                                <li class="list-group-item">
                                    <strong>{{ $log->from_status === null ? ($item->user_id ? 'Submitted' : 'Created') : ($log->from_status === $log->to_status ? 'Edited' : $log->from_status->label().' → '.$log->to_status->label()) }}</strong>
                                    <div class="text-body-secondary">{{ $log->actor?->name ?? 'System' }} · {{ $log->created_at?->format('j M Y, H:i') }}</div>
                                    @if ($log->note && $log->note !== 'Edited')<div class="mt-1">{{ $log->note }}</div>@endif
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-admin.layout>
