@php
    $columns = $report->columns();
    $format = function ($value, string $type) {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2),
            'number' => number_format((float) $value),
            'date' => \Carbon\CarbonImmutable::parse($value)->timezone(config('app.timezone'))->format('Y-m-d'),
            'datetime' => \Carbon\CarbonImmutable::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            default => (string) $value,
        };
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { color: #555; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ddd; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; font-weight: bold; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; border-top: 1px solid #000; }
    </style>
</head>
<body>
    <div><strong>{{ $store['store.registered_name'] ?: config('app.name') }}</strong> · TIN {{ $store['store.tin'] }}</div>
    <h1>{{ $report->title() }}</h1>
    <div class="meta">
        @if ($report->usesDates()) {{ $filters->from->toDateString() }} to {{ $filters->to->toDateString() }} · @endif
        Printed {{ now()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($columns as $definition)
                    <th class="{{ in_array($definition[1], ['money', 'number']) ? 'num' : '' }}">{{ $definition[0] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $key => $definition)
                        <td class="{{ in_array($definition[1], ['money', 'number']) ? 'num' : '' }}">{{ $format($row[$key] ?? null, $definition[1]) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No records in this period.</td></tr>
            @endforelse
        </tbody>
        @if ($totals !== [])
            <tfoot>
                <tr>
                    @foreach ($columns as $key => $definition)
                        <td class="num">{{ array_key_exists($key, $totals) ? number_format((float) $totals[$key], 2) : ($loop->first ? 'Total' : '') }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
