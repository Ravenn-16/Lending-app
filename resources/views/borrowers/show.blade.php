@extends('layouts.app')
@section('title', 'Borrower Details')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2>{{ $borrower->name }}</h2><span class="reference-id">{{ $borrower->reference }}</span><p class="text-muted">{{ $borrower->location ?? 'No location' }}, {{ $borrower->contact_number ?? 'No phone number' }}</p></div><div class="d-flex gap-2"><a class="btn btn-warning" href="{{ route('borrowers.edit', $borrower) }}">Edit</a><a class="btn btn-outline-secondary" href="{{ route('borrowers.index') }}">Back</a></div></div>
<div class="row mb-4">@foreach(['principal_amount' => 'Money Lent', 'balance' => 'Still to Collect', 'total_paid' => 'Payments Received'] as $key => $label)<div class="col-md-4"><div class="card card-body shadow-sm"><h6 class="text-muted">{{ $label }}</h6><h3>@money($summaries->sum($key))</h3></div></div>@endforeach</div>
<div class="d-flex justify-content-between mb-3"><h4>Loans</h4><a class="btn btn-primary" href="{{ route('money.create', ['borrower_id' => $borrower->id]) }}">Start a Loan</a></div>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Loan ID</th><th>Borrowed</th><th>Amount lent</th><th>Status</th><th>Still owed</th><th>Interest</th><th>Due Date</th><th>Actions</th></tr></thead><tbody>
@forelse($borrower->loans as $loan) @php($summary = $summaries[$loan->id])
<tr><td class="reference-id">{{ $loan->reference }}</td><td>{{ $loan->date_borrowed }}</td><td>@money($loan->principal)</td><td><x-status :status="$summary['status']" /></td><td>@money($summary['balance'])</td><td>@money($summary['interest_accrued'])</td><td>{{ $summary['next_due_date'] ?? 'Not due' }}</td><td><a class="btn btn-sm btn-info" href="{{ route('loans.show', $loan) }}">View loan</a> <a class="btn btn-sm btn-success" href="{{ route('money.create', ['loan_id' => $loan->id]) }}">Payment</a></td></tr>
@empty<tr><td colspan="8">No loans yet.</td></tr>@endforelse
</tbody></table></div>
@if($borrower->loans->isEmpty())<form method="post" action="{{ route('borrowers.destroy', $borrower) }}" class="mt-4">@csrf @method('DELETE')<button class="btn btn-outline-danger" data-confirm-delete type="submit">Delete Borrower</button></form>@endif
@endsection
