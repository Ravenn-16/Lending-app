<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up your email - Lending System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
</head><body class="login-page"><main class="login-container">
<div class="login-header"><h1>Set up your email</h1><p>Use an email address that belongs to you. We will send a code here each time you sign in.</p></div>
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('login.email') }}">@csrf
<label class="form-label" for="email">Your email address</label>
<input class="form-control mb-3" id="email" name="email" type="email" autocomplete="email" maxlength="254" value="{{ old('email') }}" required autofocus>
<label class="form-label" for="email_confirmation">Type your email again</label>
<input class="form-control mb-3" id="email_confirmation" name="email_confirmation" type="email" autocomplete="email" maxlength="254" required>
<p class="text-muted small">Your email will only be saved after you enter the code we send. If you mistype it, return to sign in and start again.</p>
<button class="btn btn-primary w-100" type="submit">Send my verification code</button>
</form>
<form method="post" action="{{ route('login.code.cancel') }}" class="mt-3">@csrf<button class="btn btn-outline-secondary w-100" type="submit">Back to sign in</button></form>
</main></body></html>
