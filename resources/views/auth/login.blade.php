<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login - Lending System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
</head><body class="login-page"><div class="login-container">
<div class="login-header"><i class="fas fa-coins" aria-hidden="true"></i><h1>Lending System</h1><p>Admin Login</p></div>
@include('partials.messages')
<form method="post" action="{{ route('login') }}">@csrf
<div class="form-group"><label for="username" class="form-label">Username</label><input class="form-control" id="username" name="username" maxlength="100" value="{{ old('username') }}" placeholder="Enter your username" required autofocus autocomplete="username"></div>
<div class="form-group"><label for="password" class="form-label">Password</label><input class="form-control" id="password" name="password" type="password" maxlength="72" placeholder="Enter your password" required autocomplete="current-password"></div>
<button class="btn btn-primary btn-login" type="submit"><i class="fas fa-sign-in-alt me-2"></i>Login</button>
</form><p class="form-text text-muted">@if(config('login_code.enabled'))After your password, we will email a verification code to the owner.@else Use your administrator account to continue. @endif</p>
</div>
<script>window.formErrors = {{ Illuminate\Support\Js::from($errors->messages()) }};</script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
</body></html>
