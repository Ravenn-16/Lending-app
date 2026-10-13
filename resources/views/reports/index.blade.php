@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 report-controls">
<div><span class="eyebrow">LENDING INSIGHTS</span><h2>Reports</h2><p class="text-muted mb-0">See how much you lent, how much came back, and how much people still owe.</p></div>
<div class="d-flex gap-2 report-controls">
<a class="btn btn-outline-secondary" href="{{ route('reports.index', array_merge($filters, ['format' => 'csv'])) }}">Download spreadsheet (CSV)</a>
@if($printing)<button class="btn btn-primary" type="button" onclick="window.print()">Print report</button><a class="btn btn-outline-secondary" href="{{ route('reports.index', $filters) }}">Back</a>
@else<a class="btn btn-primary" href="{{ route('reports.index', array_merge($filters, ['format' => 'print'])) }}">Print or save as PDF</a>@endif
</div></div>
@if(!$printing)
<details class="help-panel mb-3 report-controls"><summary>Which report should I choose?</summary><ul><li><strong>Payments received:</strong> see money paid back, minus canceled payments.</li><li><strong>Money lent:</strong> see the original amounts you lent.</li><li><strong>Interest added to loans:</strong> see interest charged, even if it has not been paid.</li><li><strong>Money still owed:</strong> see what each person still needs to pay.</li><li><strong>Payments due soon or late:</strong> see who needs a payment reminder.</li></ul><p>Download spreadsheet saves a CSV file you can open in Excel or another spreadsheet app. Print or save as PDF opens a full report, then use Print report.</p></details>
<form method="get" action="{{ route('reports.index') }}" class="card card-body mb-4 report-controls">
<div class="row g-3 align-items-end">
<div class="col-md-6 col-xl-3"><label class="form-label" for="report-type">1. What do you want to see?</label><select class="form-select" name="type" id="report-type">@foreach($types as $code => $label)<option value="{{ $code }}" @selected($filters['type'] === $code)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6 col-xl-3"><label class="form-label" for="report-search">Person (optional)</label><input class="form-control" name="search" id="report-search" maxlength="255" value="{{ $filters['search'] }}" placeholder="All borrowers"></div>
<div class="col-6 col-xl-3"><label class="form-label" for="report-from">2. Start date</label><input class="form-control" type="date" id="report-from" name="from" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ $filters['from'] }}" required></div>
<div class="col-6 col-xl-3"><label class="form-label" for="report-to">3. End date</label><input class="form-control" type="date" id="report-to" name="to" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ $filters['to'] }}" required></div>
<div class="col-12"><p class="form-help mb-2">Choose your report and dates, then select Show report. Leave the person blank to include everyone. For money still owed or payments due, the list shows what was owed on the end date.</p><button class="btn btn-primary" type="submit">Show report</button> <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">Reset to this month</a></div>
</div></form>
@endif
<article class="report-document">
<header class="report-document-header">
<div><div class="report-company">Lending System</div><h2>{{ $types[$filters['type']] }} Report</h2><p class="mb-0">@if($snapshot)As of {{ $filters['to'] }}@elseFor the period {{ $filters['from'] }} to {{ $filters['to'] }}@endif</p></div>
<span class="reference-id">{{ $reportCode }}</span>
</header>
<dl class="report-metadata">
<div><dt>Prepared by</dt><dd>{{ $preparedBy }}</dd></div><div><dt>Generated on</dt><dd>{{ $generatedAt }}</dd></div>
<div><dt>People included</dt><dd>{{ $filters['search'] ?: 'All borrowers' }}</dd></div><div><dt>Currency / number of entries</dt><dd>PHP / {{ number_format($report['rows']->count()) }}</dd></div>
</dl>
<h3 class="report-section-title">Money summary</h3><p class="text-muted small">Money lent, received, and interest added: {{ $filters['from'] }} to {{ $filters['to'] }}. Money still owed: on {{ $filters['to'] }}.</p>
<div class="row g-3 mb-4 report-metrics">
@foreach(['releases' => 'Money lent in period', 'collections' => 'Payments received, less cancellations', 'interest' => 'Interest added in period', 'outstanding' => 'Still owed on end date'] as $key => $label)
<div class="col-sm-6 col-xl-3"><div class="card card-body h-100"><h6 class="text-muted small">{{ $label }}</h6><h3 class="metric-value mb-0">@money($report['totals'][$key] / 100)</h3></div></div>
@endforeach
</div>
<section class="card report-detail">
<div class="card-header"><h4 class="mb-1">{{ $types[$filters['type']] }}</h4><p class="text-muted small mb-0">@if($snapshot)As of {{ $filters['to'] }}@else{{ $filters['from'] }} through {{ $filters['to'] }}@endif &middot; {{ $filters['search'] !== '' ? 'Borrower: '.$filters['search'] : 'All borrowers' }} &middot; {{ $report['rows']->count() }} records</p></div>
<div class="card-body py-3"><p class="text-muted small mb-0">
@if($filters['type'] === 'collections')Payments received minus payments canceled during these dates. The total can be below zero when an older payment is canceled.
@elseif($filters['type'] === 'interest')Interest charged to the loans, including monthly interest calculated by the app. These amounts may not have been paid yet.
@elseif($filters['type'] === 'due')Loans that were late or due within three days of the end date. The earliest due dates appear first.
@elseif($filters['type'] === 'outstanding')Money people still owed on the end date, including interest.
@elseThe original money lent during these dates. Interest is not included.
@endif
If you later add or correct a payment with an earlier date, reports for that date may change.</p></div>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th scope="col">{{ $snapshot ? 'Due date' : 'Date' }}</th><th scope="col">Borrower</th><th scope="col">Loan</th><th scope="col">Record number</th><th scope="col">Details</th><th scope="col" class="text-end">Amount (PHP)</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td class="text-nowrap">{{ $row['date'] }}</td><td>{{ $row['borrower'] }}<small class="d-block reference-id text-muted">{{ $row['borrower_reference'] }}</small></td><td><a href="{{ route('loans.show', $row['loan_id']) }}">{{ $row['loan_reference'] }}</a></td><td class="reference-id">{{ $row['transaction_reference'] ?: '—' }}@if($row['payment_reference'])<small class="d-block">{{ $row['payment_reference'] }}</small>@endif</td><td>{{ $row['description'] }}</td><td class="text-end text-nowrap">@money($row['amount'] / 100)</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">No records match this report. Try another date range or borrower.</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="5">Total for this report (all pages)</th><th class="text-end text-nowrap">@money($report['reportTotal'] / 100)</th></tr></tfoot></table></div>
</section>
@if(!$printing)<div class="mt-3">{{ $rows->links() }}</div>@endif
<footer class="report-footer"><span>End of report @if(!$printing)&middot; Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}@endif</span><span>{{ $reportCode }} &middot; All amounts in PHP</span></footer>
</article>
<div class="report-controls">@include('partials.reference-help')</div>
@endsection
