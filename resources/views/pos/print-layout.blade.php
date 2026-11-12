{{--
    80 mm thermal printout. The page prints itself on load; set the thermal
    printer as the browser's default printer and turn on "open cash drawer
    after printing" in its driver so the drawer kicks with each receipt.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: 74mm; font: 11px/1.35 "Courier New", ui-monospace, monospace; color: #000; background: #fff; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .big { font-size: 14px; }
        .rule { border-top: 1px dashed #000; margin: 4px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .row > span:first-child { flex: 1; }
        .item { margin: 2px 0; }
        .muted { font-size: 10px; }
        .stamp { border: 2px solid #000; padding: 2px; margin: 4px 0; text-align: center; font-weight: bold; letter-spacing: 2px; }
        .signature { margin-top: 18px; border-top: 1px solid #000; padding-top: 2px; text-align: center; }
        .actions { margin: 12px 0; display: flex; gap: 8px; justify-content: center; }
        .actions button { font: inherit; padding: 6px 10px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="center">
        <div class="bold">{{ $store['store.registered_name'] ?: config('app.name') }}</div>
        @if ($store['store.trade_name'])<div>{{ $store['store.trade_name'] }}</div>@endif
        <div>{{ $store['store.address'] }}</div>
        <div>{{ $store['store.vat_registered'] ? 'VAT REG TIN' : 'NON-VAT REG TIN' }}: {{ $store['store.tin'] }}</div>
        @yield('terminal')
    </div>
    <div class="rule"></div>

    @yield('content')

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
