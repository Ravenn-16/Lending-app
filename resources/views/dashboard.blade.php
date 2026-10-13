@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@if($borrowerCount === 0)
<aside class="getting-started mb-4" aria-label="Getting started"><h3>Start with your first person</h3><ol><li><strong>Add a person</strong> &mdash; enter their name and contact details.</li><li><strong>Start a loan</strong> &mdash; choose the person and enter the amount lent.</li><li><strong>Record a payment</strong> &mdash; enter the money they pay back.</li></ol><a class="btn btn-primary" href="{{ route('borrowers.create') }}">Add your first person</a></aside>
@endif
<div class="dashboard-intro"><div><span class="eyebrow">YOUR LENDING OVERVIEW</span><h2 class="mb-2">Good day</h2><p class="text-muted mb-0">Here is your lending activity at a glance.</p></div><span class="dashboard-date"><i class="far fa-calendar me-2" aria-hidden="true"></i>{{ today()->format('l, M j, Y') }}</span></div>
<div class="row g-3 dashboard-metrics">
@foreach(['principal' => 'Money Lent', 'balance' => 'Still to Collect', 'paid' => 'Payments Received', 'interest' => 'Interest Added'] as $key => $label)
<div class="col-md-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body"><div class="metric-heading"><h6 class="text-muted mb-0">{{ $label }}</h6><span class="metric-icon" aria-hidden="true"><i class="fas fa-{{ ['principal' => 'wallet', 'balance' => 'coins', 'paid' => 'money-bill-wave', 'interest' => 'chart-line'][$key] }}"></i></span></div><h3 class="metric-value mb-0">@money($totals[$key])</h3></div></div></div>
@endforeach
</div><div class="section-heading"><h4>What needs attention</h4><span class="text-muted">Loan status at a glance</span></div><div class="row g-3">
@foreach(['ACTIVE' => 'Active Loans', 'DUE_SOON' => 'Due Soon', 'OVERDUE' => 'Overdue Loans', 'FULLY_PAID' => 'Fully Paid'] as $code => $label)
<div class="col-6 col-xl-3"><a href="{{ route('loans.index', ['status' => $code]) }}" class="status-card card text-decoration-none h-100"><div class="card-body"><h6 class="text-muted"><span class="status-dot status-{{ strtolower($code) }}" aria-hidden="true"></span>{{ $label }}</h6><div class="d-flex align-items-center justify-content-between"><h2 class="mb-0">{{ $counts[$code] }}</h2><span class="status-arrow" aria-hidden="true">&rarr;</span></div></div></a></div>
@endforeach
</div><div class="card card-body quick-actions"><div><span class="eyebrow">KEEP THINGS MOVING</span><h4>Quick actions</h4><p class="text-muted mb-0">{{ $borrowerCount }} people in your lending records.</p></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ route('money.create') }}"><i class="fas fa-plus me-2" aria-hidden="true"></i>Start a Loan</a><a class="btn btn-success" href="{{ route('money.create', ['mode' => 'payment']) }}">Record a Payment</a><a class="btn btn-outline-secondary" href="{{ route('borrowers.create') }}">Add a Person</a></div></div>
@endsection
