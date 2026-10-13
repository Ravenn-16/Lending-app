<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Check your email - Lending System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
</head><body class="login-page"><main class="login-container">
<div class="login-header"><h1>Check your email</h1><p>Enter the 6-digit code sent to your email address. If this is your first sign-in, this also confirms and saves your email.</p></div>
@if(session('code_sent'))<p role="status">A new code has been sent. Use the latest email.</p>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('login.code') }}">@csrf
<label class="form-label" for="code">Email verification code</label>
<input class="form-control mb-3" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus aria-describedby="code-help">
<p id="code-help" class="text-muted small">The code expires after 10 minutes. Check your spam folder if the email is missing.</p>
<button class="btn btn-primary w-100" type="submit">Verify and open my account</button>
</form>
<form method="post" action="{{ route('login.code.resend') }}" class="mt-3">@csrf<button class="btn btn-outline-secondary w-100" type="submit">Send a new code</button></form>
<p class="text-muted small mt-2">Wait at least one minute before requesting another code.</p>
<form method="post" action="{{ route('login.code.cancel') }}">@csrf<button class="btn btn-link w-100" type="submit">Back to sign in</button></form>
</main></body></html>
