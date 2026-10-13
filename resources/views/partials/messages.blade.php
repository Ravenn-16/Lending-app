<div id="notification-region" class="notification-region" aria-label="Notifications" aria-live="polite" aria-relevant="additions">
@foreach(['success', 'error', 'warning', 'info'] as $type)
@if(session($type))
<div class="notification alert alert-{{ $type === 'error' ? 'danger' : $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}" @if($type === 'success' || $type === 'info') data-auto-dismiss @endif>
<div>{{ session($type) }}</div><button type="button" class="btn-close" data-dismiss-notification aria-label="Dismiss notification"></button>
</div>
@endif
@endforeach
@if($errors->any())
<div class="notification alert alert-danger" role="alert"><div><strong>Please check the following:</strong><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div><button type="button" class="btn-close" data-dismiss-notification aria-label="Dismiss notification"></button></div>
@endif
@if($dueSoonCount > 0)
<div class="notification alert alert-warning" role="status"><div><strong>Upcoming payments</strong><p class="mb-1">{{ $dueSoonCount }} {{ $dueSoonCount === 1 ? 'loan is' : 'loans are' }} due today or within the next 3 days.</p><a class="alert-link" href="{{ route('loans.index', ['status' => 'DUE_SOON']) }}">View loans due soon</a></div><button type="button" class="btn-close" data-dismiss-notification aria-label="Dismiss due-date reminder"></button></div>
@endif
</div>
