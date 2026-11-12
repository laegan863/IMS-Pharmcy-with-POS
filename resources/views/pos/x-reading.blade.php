@extends('pos.print-layout')

@php($peso = fn ($value) => number_format((float) $value, 2))

@section('title', 'X-reading '.$shift->number)

@section('terminal')
    <div class="muted">MIN: {{ $shift->terminal->machine_identification_number ?? '—' }} &nbsp; PTU: {{ $shift->terminal->permit_to_use_number ?? '—' }}</div>
@endsection

@section('content')
    <div class="center bold big">X-READING</div>
    <div class="row"><span>Shift</span><span>{{ $shift->number }}</span></div>
    <div class="row"><span>Cashier</span><span>{{ $shift->user->name }}</span></div>
    <div class="row"><span>Terminal</span><span>{{ $shift->terminal->code }}</span></div>
    <div class="row"><span>Opened</span><span>{{ $shift->opened_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span></div>
    <div class="row"><span>Closed</span><span>{{ $shift->closed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span></div>
    <div class="rule"></div>
    <div class="row"><span>Invoices</span><span>{{ $reading['invoice_count'] ?? 0 }}</span></div>
    <div class="row"><span>From</span><span>{{ $reading['first_invoice'] ?? '—' }}</span></div>
    <div class="row"><span>To</span><span>{{ $reading['last_invoice'] ?? '—' }}</span></div>
    <div class="rule"></div>
    <div class="row"><span>Gross sales</span><span>{{ $peso($reading['gross_sales'] ?? 0) }}</span></div>
    <div class="row"><span>Senior/PWD discounts</span><span>{{ $peso($reading['statutory_discounts'] ?? 0) }}</span></div>
    <div class="row bold"><span>Net sales</span><span>{{ $peso($reading['net_sales'] ?? 0) }}</span></div>
    <div class="row"><span>Voids ({{ $reading['void_count'] ?? 0 }})</span><span>{{ $peso($reading['voids'] ?? 0) }}</span></div>
    <div class="row"><span>Refunds ({{ $reading['refund_count'] ?? 0 }})</span><span>{{ $peso($reading['refunds'] ?? 0) }}</span></div>
    <div class="rule"></div>
    <div class="row"><span>VATable sales</span><span>{{ $peso($reading['vatable_sales'] ?? 0) }}</span></div>
    <div class="row"><span>VAT amount</span><span>{{ $peso($reading['vat_amount'] ?? 0) }}</span></div>
    <div class="row"><span>VAT-exempt sales</span><span>{{ $peso($reading['vat_exempt_sales'] ?? 0) }}</span></div>
    <div class="row"><span>Zero-rated sales</span><span>{{ $peso($reading['zero_rated_sales'] ?? 0) }}</span></div>
    <div class="rule"></div>
    @foreach (($reading['payments'] ?? []) as $method => $amount)
        <div class="row"><span>{{ \App\Models\Payment::METHODS[$method] ?? $method }}</span><span>{{ $peso($amount) }}</span></div>
    @endforeach
    <div class="rule"></div>
    <div class="row"><span>Opening cash</span><span>{{ $peso($reading['opening_cash'] ?? 0) }}</span></div>
    <div class="row"><span>Cash in</span><span>{{ $peso($reading['cash_in'] ?? 0) }}</span></div>
    <div class="row"><span>Cash out</span><span>{{ $peso($reading['cash_out'] ?? 0) }}</span></div>
    <div class="row bold"><span>Expected cash</span><span>{{ $peso($shift->expected_cash) }}</span></div>
    <div class="row bold"><span>Counted cash</span><span>{{ $peso($shift->counted_cash) }}</span></div>
    <div class="row bold"><span>Variance</span><span>{{ $peso($shift->variance) }}</span></div>
    <div class="signature">Cashier</div>
    <div class="signature">Supervisor</div>
    <div class="actions"><a href="{{ route('pos.shifts.create') }}">Open a new shift</a></div>
@endsection
