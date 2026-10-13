@extends('layouts.app')
@section('title', $borrower->exists ? 'Edit Borrower' : 'Add Borrower')
@section('content')
<h2>{{ $borrower->exists ? 'Edit person' : 'Add a person' }}</h2>
@if($borrower->exists)<p class="reference-id text-muted">{{ $borrower->reference }}</p>@endif
<form method="post" action="{{ $borrower->exists ? route('borrowers.update', $borrower) : route('borrowers.store') }}" class="card card-body" style="max-width:700px">@csrf @unless($borrower->exists)<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}">@endunless @if($borrower->exists) @method('PUT') @endif
<label class="form-label" for="name">Name *</label><input class="form-control mb-3" id="name" name="name" maxlength="255" value="{{ old('name', $borrower->name) }}" required>
@php
$addresses = app(\App\Services\PhilippineAddress::class);
$provinceCode = old('province_code', $borrower->province_code) ?? '';
$cityCode = old('city_code', $borrower->city_code) ?? '';
$barangayCode = old('barangay_code', $borrower->barangay_code) ?? '';
$provinceCode = is_string($provinceCode) ? $provinceCode : '';
$cityCode = is_string($cityCode) ? $cityCode : '';
$barangayCode = is_string($barangayCode) ? $barangayCode : '';
@endphp
<fieldset id="address-fields" class="mb-3" data-cities-url="{{ route('addresses.cities') }}" data-barangays-url="{{ route('addresses.barangays') }}">
<legend class="fs-6">Location</legend>
@if($borrower->location && !$borrower->province_code)<p class="text-muted small">Current address: {{ $borrower->location }}. Select an address below to replace it.</p>@endif
<label class="form-label" for="province_code">Province or area</label>
<select class="form-select mb-3" name="province_code" id="province_code">
<option value="">Choose a province or area</option>
@foreach($addresses->provinces() as $province)<option value="{{ $province['code'] }}" @selected($provinceCode === $province['code'])>{{ $province['name'] }}</option>@endforeach
</select>
<label class="form-label" for="city_code">City or municipality</label>
<select class="form-select mb-3" name="city_code" id="city_code" @disabled(!$provinceCode) @required($provinceCode)>
<option value="">Choose a city or municipality</option>
@foreach($addresses->cities($provinceCode) as $city)<option value="{{ $city['code'] }}" @selected($cityCode === $city['code'])>{{ $city['name'] }}</option>@endforeach
</select>
<label class="form-label" for="barangay_code">Barangay</label>
<select class="form-select" name="barangay_code" id="barangay_code" @disabled(!$cityCode) @required($provinceCode)>
<option value="">Choose a barangay</option>
@foreach($addresses->barangays($cityCode) as $barangay)<option value="{{ $barangay['code'] }}" @selected($barangayCode === $barangay['code'])>{{ $barangay['name'] }}</option>@endforeach
</select>
<p id="address-status" class="form-text mb-0" role="status" aria-live="polite">Location is optional. Select all three fields when providing an address. Metro Manila is listed as an area.</p>
<button id="address-retry" type="button" class="btn btn-outline-secondary btn-sm mt-2" hidden>Retry loading</button>
<noscript><p>Enable JavaScript to change the linked address selections.</p></noscript>
</fieldset>
<label class="form-label" for="contact_number">Phone number</label><input class="form-control mb-3" type="tel" id="contact_number" name="contact_number" maxlength="50" value="{{ old('contact_number', $borrower->contact_number) }}">
<div class="d-flex gap-2"><button class="btn btn-primary">Save Person</button><a class="btn btn-outline-secondary" href="{{ route('borrowers.index') }}">Cancel</a></div>
</form>
@endsection

@push('scripts')<script src="{{ asset('assets/js/address.js') }}"></script>@endpush
