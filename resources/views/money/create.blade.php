@extends('layouts.app')

@section('title', 'Loans and Payments')

@section('content')

<h2>Loans and payments</h2><p class="text-muted">Choose an action below. Add the person on the People page first if their name is not listed.</p>

<ul class="nav nav-tabs mb-4"><li class="nav-item"><a class="nav-link {{ $mode === 'new_loan' ? 'active' : '' }}" href="{{ route('money.create', ['mode' => 'new_loan', 'borrower_id' => $borrowerId ?: null]) }}">Start a Loan</a></li><li class="nav-item"><a class="nav-link {{ $mode === 'payment' ? 'active' : '' }}" href="{{ route('money.create', ['mode' => 'payment', 'borrower_id' => $borrowerId ?: null]) }}">Record Money Received</a></li></ul>

<div class="row"><div class="col-lg-8"><div class="card card-body">

@if($mode === 'new_loan')

<form method="post" action="{{ route('loans.store') }}">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="mode" value="new_loan">

<label for="borrower_id" class="form-label">Who is borrowing? *</label><select class="form-select mb-3" id="borrower_id" name="borrower_id" required><option value="">Choose a person</option>@foreach($borrowers as $borrower)<option value="{{ $borrower->id }}" @selected((string)$borrowerId === (string)$borrower->id)>{{ $borrower->reference }} — {{ $borrower->name }}</option>@endforeach</select>

<label for="principal" class="form-label">How much was lent? &#8369; *</label><input class="form-control mb-3" type="number" id="principal" name="principal" max="9090909090.90" min="0.01" step="0.01" value="{{ old('principal') }}" required>

<label for="date_borrowed" class="form-label">When was the money lent? *</label><input class="form-control mb-3" type="date" id="date_borrowed" name="date_borrowed" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ old('date_borrowed', today()->toDateString()) }}" required>

<button class="btn btn-primary" type="submit">Save Loan</button>

</form>

@else

<form method="post" action="{{ route('payments.store') }}" id="payment-form" data-loans-url="{{ route('api.borrower-loans', ['borrower' => '__ID__']) }}" data-info-url="{{ route('api.loan-info', ['loan' => '__ID__']) }}" data-selected-loan="{{ old('loan_id', $selectedLoan?->id) }}">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="mode" value="payment">

<label for="borrower_select" class="form-label">Who is paying? *</label><select class="form-select mb-3" id="borrower_select" name="borrower_id" required><option value="">Choose a person</option>@foreach($borrowers as $borrower)<option value="{{ $borrower->id }}" @selected((string)$borrowerId === (string)$borrower->id)>{{ $borrower->reference }} — {{ $borrower->name }}</option>@endforeach</select>

<label for="loan_id" class="form-label">Which loan? *</label><select class="form-select mb-3" id="loan_id" name="loan_id" required><option value="">Choose a loan</option></select>

<label for="payment_date" class="form-label">When was it received? *</label><input class="form-control mb-3" type="date" id="payment_date" name="payment_date" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ old('payment_date', today()->toDateString()) }}" required>

<label for="payment_amount" class="form-label">How much was received? &#8369; *</label><div class="d-flex gap-2 mb-3"><input class="form-control" type="number" id="payment_amount" name="payment_amount" max="9999999999.99" min="0.01" step="0.01" value="{{ old('payment_amount') }}" required><button class="btn btn-outline-secondary" type="button" id="payInFullBtn" disabled>Use full amount owed</button></div>

<div id="loanInfo" class="alert alert-info d-none" aria-live="polite"><span id="currentBalance"></span><br><span id="loanStatus"></span></div>

<button class="btn btn-primary" type="submit" id="savePaymentBtn" disabled>Save Payment</button>

</form>

@endif

</div></div><div class="col-lg-4"><div class="card card-body"><h5>Loan rules</h5><p>A new loan starts with 10% interest. For example, lending PHP 1,000 makes the amount owed PHP 1,100. Each month, another 10% is added to the remaining balance after payments made that day.</p><p>Payment is due one month after borrowing. Paying part of the amount does not move the due date.</p><p class="text-muted mb-0">Use full amount owed fills in the amount needed to finish paying the loan on your selected payment date.</p></div></div></div>

@endsection

@push('scripts')<script src="{{ asset('assets/js/payments.js') }}"></script>@endpush

