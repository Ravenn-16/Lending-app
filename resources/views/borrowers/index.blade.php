@extends('layouts.app')
@section('title', 'People')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2>People</h2><p class="text-muted">View people, loans, and outstanding balances.</p></div><a class="btn btn-primary" href="{{ route('borrowers.create') }}">Add a Person</a></div>
<form method="get" class="d-flex gap-2 mb-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search by name, location or phone" aria-label="Search borrowers"><button class="btn btn-outline-secondary">Search</button>@if($search)<a class="btn btn-outline-secondary" href="{{ route('borrowers.index') }}">Clear</a>@endif</form>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Borrower ID</th><th>Name</th><th>Location</th><th>Phone</th><th>Loans</th><th>Still to Collect</th><th>Actions</th></tr></thead><tbody>
@forelse($borrowers as $borrower)<tr><td class="reference-id">{{ $borrower->reference }}</td><td><a href="{{ route('borrowers.show', $borrower) }}">{{ $borrower->name }}</a></td><td>{{ $borrower->location ?? 'Not provided' }}</td><td>{{ $borrower->contact_number ?? 'Not provided' }}</td><td>{{ $borrower->loans->count() }}</td><td>@money($borrower->balance)</td><td><a class="btn btn-sm btn-info" href="{{ route('borrowers.show', $borrower) }}">View details</a> <a class="btn btn-sm btn-warning" href="{{ route('borrowers.edit', $borrower) }}">Edit person</a></td></tr>
@empty<tr><td colspan="7">No borrowers found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $borrowers->links() }}</div>
@endsection
