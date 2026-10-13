@extends('layouts.app')
@section('title', 'Loan History')
@section('content')
<h2>{{ $loan->borrower->name }}, {{ $loan->reference }}</h2>
<p>Borrowed {{ $loan->date_borrowed }}, Still owed: <strong>@money($summary['balance'])</strong>, <x-status :status="$summary['status']" /></p>
<div class="d-flex gap-2 mb-4"><a class="btn btn-outline-secondary" href="{{ route('borrowers.show', $loan->borrower) }}">Back to person</a><a class="btn btn-primary" href="{{ route('money.create', ['loan_id' => $loan->id]) }}">Record payment</a></div>
@if($summary['has_overpayment'])<div class="alert alert-warning">Payments exceeded the amount owed at some point. Review the payment history. An amount below zero means the person has paid extra.</div>@endif
<h4>Payment history</h4><div class="table-responsive mb-4"><table class="table table-striped"><thead><tr><th>Payment ID</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($loan->payments->sortByDesc('id') as $payment)<tr><td class="reference-id">{{ $payment->reference }}</td><td>{{ $payment->payment_date }}</td><td>@money($payment->amount)</td><td>{{ $payment->status === 'POSTED' ? 'Recorded' : 'Canceled' }}</td><td>@if($payment->status === 'POSTED')<a class="btn btn-sm btn-outline-danger" href="{{ route('payments.void', $payment) }}">Cancel this payment</a>@endif</td></tr>@empty<tr><td colspan="5">No payments recorded.</td></tr>@endforelse
</tbody></table></div>
<h4>Balance history</h4><p class="text-muted">This shows how the amount owed changed. Payments reduce it; interest increases it. Canceling a payment adds that amount back to the loan today.</p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Transaction ID</th><th>Date</th><th>Type</th><th>Amount</th><th>Amount still owed</th><th>Details</th></tr></thead><tbody>
@foreach($summary['history'] as $entry)<tr><td class="reference-id">{{ !empty($entry['calculated']) || empty($entry['id']) ? 'Calculated' : \App\Support\Reference::format('LedgerEntry', (int) $entry['id']) }}</td><td>{{ $entry['effective_date'] }}</td><td>{{ ['PRINCIPAL' => 'Money lent', 'PAYMENT' => 'Payment received', 'PAYMENT_REVERSAL' => 'Payment canceled', 'INTEREST' => 'Interest added', 'ADJUSTMENT' => 'Balance correction'][$entry['type']] ?? $entry['type'] }}</td><td>@money($entry['amount'])</td><td>@money($entry['running_balance'])</td><td>{{ $entry['notes'] ?? 'Older record; person who entered it is not listed' }}</td></tr>@endforeach
</tbody></table></div>
<div class="report-controls">@include('partials.reference-help')</div>
@endsection
