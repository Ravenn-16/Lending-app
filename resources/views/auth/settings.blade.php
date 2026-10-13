@extends('layouts.app')
@section('title', 'Account Settings')
@section('content')
@if(config('login_code.enabled'))<div class="card card-body mb-4"><h2 class="h5">Your sign-in email</h2><p class="mb-1">{{ app(\App\Services\LoginCodeService::class)->email(auth()->user()) }}</p><p class="text-muted small mb-0">Login codes are sent here. Contact the person who maintains the app if you need to change this address.</p></div>@endif
<h2>Change password</h2>
<form method="post" action="{{ route('settings.password') }}" class="card card-body" style="max-width:540px">@csrf @method('PUT')
<label for="current_password" class="form-label">Current password</label><input id="current_password" name="current_password" type="password" maxlength="72" class="form-control mb-3" required autocomplete="current-password">
<label for="password" class="form-label">New password (at least 12 characters)</label><input id="password" name="password" type="password" maxlength="72" class="form-control mb-3" required minlength="12" autocomplete="new-password">
<label for="password_confirmation" class="form-label">Type the new password again</label><input id="password_confirmation" name="password_confirmation" type="password" maxlength="72" class="form-control mb-3" required autocomplete="new-password">
<button class="btn btn-primary" type="submit">Change password</button></form>
@endsection
