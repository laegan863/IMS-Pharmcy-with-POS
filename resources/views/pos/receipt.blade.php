@extends('pos.print-layout')

@php
    $peso = fn ($value) => number_format((float) $value, 2);
    $methods = \App\Models\Payment::METHODS;
@endphp

@section('title', 'Invoice '.$sale->number)

@section('terminal')
    <div class="muted">MIN: {{ $sale->terminal->machine_identification_number ?? '—' }} &nbsp; SN: {{ $sale->terminal->serial_number ?? '—' }}</div>
    <div class="muted">PTU No.: {{ $sale->terminal->permit_to_use_number ?? '—' }}</div>
@endsection

@section('content')
    @if ($isReprint)
        <div class="stamp">REPRINT</div>
    @endif

    <div class="center bold big">SALES INVOICE</div>
    <div class="row"><span>Invoice No.</span><span class="bold">{{ $sale->number }}</span></div>
    <div class="row"><span>Date</span><span>{{ $sale->completed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span></div>
    <div class="row"><span>Cashier</span><span>{{ $sale->cashier->name }}</span></div>
    <div class="row"><span>Terminal</span><span>{{ $sale->terminal->code }}</span></div>
    @if ($sale->status !== 'completed')
        <div class="stamp">{{ strtoupper($sale->status) }}</div>
    @endif
    <div class="rule"></div>

    @foreach ($sale->lines as $line)
        <div class="item">
            <div>{{ $line->description }}</div>
            <div class="row">
                <span>&nbsp;&nbsp;{{ $line->quantity }} x {{ $peso($line->unit_price) }}{{ $line->tax_class->value === 'vatable' && ! $line->statutory_discount_applied ? ' V' : ($line->tax_class->value === 'zero_rated' ? ' Z' : ' E') }}</span>
                <span>{{ $peso($line->gross_amount) }}</span>
            </div>
            @if ((float) $line->discount_amount > 0)
                <div class="row muted"><span>&nbsp;&nbsp;Less {{ strtoupper($sale->discount_type) }} 20%</span><span>-{{ $peso($line->discount_amount) }}</span></div>
            @endif
        </div>
    @endforeach

    <div class="rule"></div>
    <div class="row"><span>{{ $sale->lines->sum('quantity') }} item(s)</span><span></span></div>
    <div class="row"><span>Gross amount</span><span>{{ $peso($sale->gross_amount) }}</span></div>
    @if ((float) $sale->statutory_discount > 0)
        @php
            $vatRemoved = bcsub((string) $sale->gross_amount, bcadd(bcadd(bcadd((string) $sale->vatable_sales, (string) $sale->vat_amount, 2), (string) $sale->vat_exempt_sales, 2), (string) $sale->zero_rated_sales, 2), 2);
        @endphp
        @if ((float) $vatRemoved > 0)
            <div class="row"><span>Less: VAT exemption</span><span>-{{ $peso($vatRemoved) }}</span></div>
        @endif
        <div class="row"><span>Less: {{ $sale->discount_type === 'senior' ? 'Senior citizen' : 'PWD' }} discount</span><span>-{{ $peso($sale->statutory_discount) }}</span></div>
    @endif
    <div class="row bold big"><span>TOTAL DUE</span><span>{{ $peso($sale->total) }}</span></div>

    @foreach ($sale->payments as $payment)
        <div class="row"><span>{{ $methods[$payment->method] ?? $payment->method }}{{ $payment->reference ? ' #'.$payment->reference : '' }}</span><span>{{ $peso($payment->amount) }}</span></div>
    @endforeach
    <div class="row"><span>Change</span><span>{{ $peso($sale->change_due) }}</span></div>

    <div class="rule"></div>
    <div class="row"><span>VATable sales</span><span>{{ $peso($sale->vatable_sales) }}</span></div>
    <div class="row"><span>VAT amount</span><span>{{ $peso($sale->vat_amount) }}</span></div>
    <div class="row"><span>VAT-exempt sales</span><span>{{ $peso($sale->vat_exempt_sales) }}</span></div>
    <div class="row"><span>Zero-rated sales</span><span>{{ $peso($sale->zero_rated_sales) }}</span></div>

    @if ($sale->discount_type !== 'none')
        <div class="rule"></div>
        <div>{{ $sale->discount_type === 'senior' ? 'Senior citizen' : 'PWD' }}: {{ $sale->discount_holder_name }}</div>
        <div>ID No.: {{ $sale->discount_id_number }}</div>
        <div class="signature">Signature</div>
    @else
        <div class="rule"></div>
        <div>Customer name: ______________________</div>
        <div>Address: ____________________________</div>
        <div>TIN: ________________________________</div>
    @endif

    <div class="rule"></div>
    <div class="center muted">
        @if ($store['receipt.header_lines'])<div>{{ $store['receipt.header_lines'] }}</div>@endif
        <div>{{ $store['receipt.footer_text'] }}</div>
        @if ($store['store.accreditation_number'])<div>Accreditation No.: {{ $store['store.accreditation_number'] }}</div>@endif
        <div>Keep this invoice for 10 years for BIR purposes.</div>
    </div>
@endsection
