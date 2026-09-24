@foreach (['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'error' => 'x-octagon'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} pa-alert d-flex gap-2 align-items-start" role="{{ $type === 'success' ? 'status' : 'alert' }}">
            <i class="bi bi-{{ $icon }} flex-shrink-0" aria-hidden="true"></i>
            <div>{{ session($type) }}</div>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="alert alert-danger pa-alert d-flex gap-2 align-items-start" role="alert" tabindex="-1" id="error-summary">
        <i class="bi bi-x-octagon flex-shrink-0" aria-hidden="true"></i>
        <div>
            <strong>Please correct the following:</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
