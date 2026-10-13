@extends('layouts.app')
@section('title', str_replace('_', ' ', $status).' Loans')
@section('content')
<h2>{{ ucwords(strtolower(str_replace('_', ' ', $status))) }} Loans</h2>
<form class="d-flex gap-2 mb-4" method="get" action="{{ route('loans.index') }}"><input type="hidden" name="status" value="{{ $status }}"><input class="form-control" name="search" maxlength="255" value="{{ $search }}" placeholder="Search borrower name" aria-label="Search borrower name"><button type="submit" class="btn btn-outline-secondary">Find</button></form>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Loan ID</th><th>Borrower</th><th>Borrowed</th><th>Amount lent</th><th>Still owed</th><th>Due Date</th><th>Actions</th></tr></thead><tbody>
@forelse($loans as $row) @php($loan = $row['loan']) @php($summary = $row['summary'])
<tr><td class="reference-id">{{ $loan->reference }}</td><td><a href="{{ route('borrowers.show', $loan->borrower) }}">{{ $loan->borrower->name }}</a><small class="d-block reference-id text-muted">{{ $loan->borrower->reference }}</small></td><td>{{ $loan->date_borrowed }}</td><td>@money($loan->principal)</td><td>@money($summary['balance'])</td><td>{{ $summary['next_due_date'] ?? 'Not due' }}</td><td><a class="btn btn-sm btn-info" href="{{ route('loans.show', $loan) }}">View loan</a> <a class="btn btn-sm btn-success" href="{{ route('money.create', ['loan_id' => $loan->id]) }}">Payment</a></td></tr>
@empty<tr><td colspan="7">No matching loans.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $loans->links() }}</div>
@endsection
