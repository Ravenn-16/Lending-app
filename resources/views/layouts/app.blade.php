<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') - Lending System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
</head><body>
<div class="d-flex" id="wrapper">
<aside class="sidebar bg-dark" id="sidebar-wrapper">
<div class="sidebar-heading p-3 border-bottom border-secondary"><h5 class="text-white mb-0"><i class="fas fa-coins me-2"></i>Lending System</h5></div>
<div class="list-group list-group-flush">
<a href="{{ route('dashboard') }}" class="list-group-item list-group-item-action list-group-item-dark {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fas fa-chart-line me-2"></i>Dashboard</a>
<a href="{{ route('borrowers.index') }}" class="list-group-item list-group-item-action list-group-item-dark {{ request()->routeIs('borrowers.*') ? 'active' : '' }}"><i class="fas fa-users me-2"></i>People</a>
<a href="{{ route('money.create', ['mode' => 'payment']) }}" class="list-group-item list-group-item-action list-group-item-dark {{ request()->routeIs('money.*') ? 'active' : '' }}"><i class="fas fa-money-bill-wave me-2"></i>Record a Payment</a>
<details class="loan-status-menu" @if(request()->routeIs('loans.*')) open @endif>
<summary class="list-group-item list-group-item-action list-group-item-dark"><span><i class="fas fa-list me-2" aria-hidden="true"></i>Loan status</span><i class="fas fa-chevron-down loan-status-chevron" aria-hidden="true"></i></summary>
<nav aria-label="Loans by status">
@foreach(['ACTIVE' => 'Not due yet', 'DUE_SOON' => 'Due within 3 days', 'OVERDUE' => 'Past due date', 'FULLY_PAID' => 'Fully Paid'] as $code => $label)
<a href="{{ route('loans.index', ['status' => $code]) }}" class="list-group-item list-group-item-action list-group-item-dark ps-4 {{ request()->routeIs('loans.index') && request('status', 'ACTIVE') === $code ? 'active' : '' }}" @if(request()->routeIs('loans.index') && request('status', 'ACTIVE') === $code) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
</details>
<a href="{{ route('transactions.index') }}" class="list-group-item list-group-item-action list-group-item-dark {{ request()->routeIs('transactions.index') ? 'active' : '' }}" @if(request()->routeIs('transactions.index')) aria-current="page" @endif><i class="fas fa-clock-rotate-left me-2" aria-hidden="true"></i>Transaction History</a>
<a href="{{ route('reports.index') }}" class="list-group-item list-group-item-action list-group-item-dark {{ request()->routeIs('reports.index') ? 'active' : '' }}" @if(request()->routeIs('reports.index')) aria-current="page" @endif><i class="fas fa-chart-pie me-2" aria-hidden="true"></i>Reports</a>
<a href="{{ route('settings') }}" class="list-group-item list-group-item-action list-group-item-dark mt-3">Account Settings</a>
<form method="post" action="{{ route('logout') }}">@csrf<button class="list-group-item list-group-item-action list-group-item-dark w-100 text-start" type="submit"><i class="fas fa-sign-out-alt me-2"></i>Logout</button></form>
</div><div class="p-3 text-white small">Signed in as {{ auth()->user()->username }}<span class="d-block reference-id">{{ auth()->user()->reference }}</span></div>
</aside>
<main id="page-content-wrapper"><nav class="navbar navbar-light bg-light border-bottom"><div class="container-fluid"><button class="btn btn-outline-secondary me-3" id="menu-toggle" type="button" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button><span class="navbar-brand me-auto">@yield('title', 'Dashboard')</span>@include('partials.notification-center')</div></nav>
<div class="container-fluid p-4">@include('partials.messages') @yield('content')</div></main>
</div>
<nav class="mobile-bottom-nav" aria-label="Main navigation">
<a href="{{ route('dashboard') }}"><i class="fas fa-house"></i><span>Home</span></a><a href="{{ route('borrowers.index') }}"><i class="fas fa-users"></i><span>People</span></a><a href="{{ route('money.create') }}"><i class="fas fa-money-bill-wave"></i><span>Money</span></a><a href="{{ route('loans.index', ['status' => 'OVERDUE']) }}"><i class="fas fa-bell"></i><span>Alerts</span></a>
</nav>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>window.formErrors = {{ Illuminate\Support\Js::from($errors->messages()) }};</script>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>@stack('scripts')
</body></html>
