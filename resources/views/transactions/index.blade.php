@extends('layouts.app')
@section('title', 'Transaction History')
@section('content')
<h2>Transaction History</h2>
<p class="text-muted">See money lent, payments received, and corrections. Select View loan to see how the amount owed changed.</p>
<form method="get" action="{{ route('transactions.index') }}" class="card card-body mb-4">
<div class="row g-3 align-items-end">
<div class="col-12 col-md-6 col-lg-3"><label class="form-label" for="transaction-search">Borrower name</label><input class="form-control" id="transaction-search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Search borrower name"></div>
<div class="col-12 col-md-6 col-lg-3"><label class="form-label" for="transaction-type">Transaction type</label><select class="form-select" id="transaction-type" name="type"><option value="">All transactions</option>@foreach($types as $code => $label)<option value="{{ $code }}" @selected(($filters['type'] ?? '') === $code)>{{ $label }}</option>@endforeach</select></div>
<div class="col-6 col-lg-3"><label class="form-label" for="transaction-from">From date</label><input class="form-control" type="date" id="transaction-from" name="from" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ $filters['from'] ?? '' }}"></div>
<div class="col-6 col-lg-3"><label class="form-label" for="transaction-to">To date</label><input class="form-control" type="date" id="transaction-to" name="to" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ $filters['to'] ?? '' }}"></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Show results</button><a class="btn btn-outline-secondary" href="{{ route('transactions.index') }}">Show all</a></div>
</div>
</form>
<p class="text-muted">{{ number_format($transactions->total()) }} {{ $transactions->total() === 1 ? 'transaction' : 'transactions' }} found. Newest transaction dates first.</p>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0"><thead><tr><th scope="col">Transaction ID</th><th scope="col">Date</th><th scope="col">Borrower</th><th scope="col">Loan</th><th scope="col">Type</th><th scope="col" class="text-end">Amount</th><th scope="col">Details</th><th scope="col">Action</th></tr></thead><tbody>
@forelse($transactions as $transaction)
<tr>
<td class="reference-id">{{ $transaction->reference }}@if($transaction->payment)<small class="d-block text-muted">{{ $transaction->payment->reference }}</small>@endif</td><td class="text-nowrap">{{ $transaction->effective_date }}</td>
<td>@if($transaction->loan?->borrower)<a href="{{ route('borrowers.show', $transaction->loan->borrower) }}">{{ $transaction->loan->borrower->name }}</a><small class="d-block reference-id text-muted">{{ $transaction->loan->borrower->reference }}</small>@else Borrower unavailable @endif</td>
<td>{{ \App\Support\Reference::format('Loan', $transaction->loan_id) }}</td>
<td>{{ $types[$transaction->type] ?? $transaction->type }}@if($transaction->type === 'PAYMENT' && $transaction->payment?->status === 'VOIDED')<span class="d-block text-muted small">Canceled</span>@endif</td>
<td class="text-end text-nowrap">@money($transaction->amount)</td>
<td>{{ $transaction->notes ?: 'No details recorded' }}</td>
<td>@if($transaction->loan)<a class="btn btn-sm btn-outline-secondary text-nowrap" href="{{ route('loans.show', $transaction->loan) }}">View loan</a>@endif</td>
</tr>
@empty<tr><td colspan="8" class="text-center text-muted py-4">No transactions found.</td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $transactions->links() }}</div>
<div class="report-controls">@include('partials.reference-help')</div>
@endsection
