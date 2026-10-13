@extends('layouts.app')
@section('title', 'Cancel a Payment')
@section('content')
<h2>Cancel a payment</h2><div class="card card-body" style="max-width:700px">
<p><strong>{{ $payment->loan->borrower->name }}</strong>, {{ $payment->loan->reference }}</p><p>Payment {{ $payment->reference }}: @money($payment->amount) on {{ $payment->payment_date }}</p><p>Amount still owed: @money($summary['balance'])</p>
@if($payment->status === 'VOIDED')<div class="alert alert-info">This payment has already been canceled.</div>
@else<div class="alert alert-warning">Only cancel a payment if it was recorded by mistake. Its amount will be added back to what the person owes today. This can also change the interest. The payment stays in the history as canceled.</div>
<form method="post" action="{{ route('payments.void', $payment) }}">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}"><label for="reason" class="form-label">Why are you canceling this payment?</label><textarea class="form-control mb-3" id="reason" name="reason" required maxlength="500">{{ old('reason') }}</textarea><button class="btn btn-danger" type="submit">Yes, cancel this payment</button></form>@endif
<a class="mt-3" href="{{ route('loans.show', $payment->loan_id) }}">Go back without changes</a></div>
@endsection
