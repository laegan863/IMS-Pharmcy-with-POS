<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Settings\Settings;
use App\Support\Decimal;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Lists the reports a user may open, shows one on screen (up to a limit)
 * and exports the full report to Excel or PDF.
 */
class ReportController extends Controller
{
    private const SCREEN_LIMIT = 2000;

    public function index(Request $request): Response
    {
        $reports = [];

        foreach (ReportRegistry::visibleTo($this->user($request)) as $key => $report) {
            $reports[] = ['key' => $key, 'title' => $report->title(), 'description' => $report->description(), 'group' => $report->group()];
        }

        return Inertia::render('reports/index', ['reports' => $reports]);
    }

    public function show(Request $request, string $key): Response
    {
        [$report, $filters] = $this->resolve($request, $key);
        $rows = [];
        $truncated = false;

        foreach ($report->rows($filters) as $row) {
            if (count($rows) === self::SCREEN_LIMIT) {
                $truncated = true;

                break;
            }

            $rows[] = $row;
        }

        if ($report->containsPersonalData()) {
            activity('privacy')->event('report-viewed')->withProperties(['report' => $key, 'from' => $filters->from->toDateString(), 'to' => $filters->to->toDateString()])->log('Personal data report opened');
        }

        return Inertia::render('reports/show', [
            'reportKey' => $key,
            'title' => $report->title(),
            'description' => $report->description(),
            'usesDates' => $report->usesDates(),
            'filters' => ['from' => $filters->from->toDateString(), 'to' => $filters->to->toDateString()],
            'columns' => array_map(fn (string $column, array $definition) => ['key' => $column, 'label' => $definition[0], 'type' => $definition[1]], array_keys($report->columns()), $report->columns()),
            'rows' => $rows,
            'totals' => $this->totals($report, $rows),
            'truncated' => $truncated,
        ]);
    }

    public function excel(Request $request, string $key): BinaryFileResponse
    {
        [$report, $filters] = $this->resolve($request, $key);
        $this->logExport($report, $key, 'xlsx');

        return Excel::download(new ReportExport($report, $filters), $this->fileName($report, $filters, 'xlsx'));
    }

    public function pdf(Request $request, string $key, Settings $settings): \Illuminate\Http\Response
    {
        [$report, $filters] = $this->resolve($request, $key);
        $this->logExport($report, $key, 'pdf');
        $rows = iterator_to_array($report->rows($filters), false);

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'filters' => $filters,
            'rows' => $rows,
            'totals' => $this->totals($report, $rows),
            'store' => $settings->all(),
        ])->setPaper('a4', count($report->columns()) > 7 ? 'landscape' : 'portrait')
            ->download($this->fileName($report, $filters, 'pdf'));
    }

    /**
     * @return array{0: Report, 1: ReportFilters}
     */
    private function resolve(Request $request, string $key): array
    {
        $report = ReportRegistry::find($key);
        abort_if($report === null, 404);

        $user = $this->user($request);
        abort_unless($user->can($report->permission()->value), 403);
        abort_if($user->branch_id === null, 403, __('Your account has no branch.'));

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$defaultFrom, $defaultTo] = $report->defaultRange();

        return [$report, new ReportFilters(
            $user->branch_id,
            $request->filled('from') ? CarbonImmutable::parse((string) $request->input('from')) : $defaultFrom,
            $request->filled('to') ? CarbonImmutable::parse((string) $request->input('to')) : $defaultTo,
        )];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function totals(Report $report, array $rows): array
    {
        $totals = [];

        foreach ($report->totalColumns() as $column) {
            $totals[$column] = Decimal::sum(array_map(fn (array $row) => is_numeric($row[$column] ?? null) ? (string) $row[$column] : '0', $rows));
        }

        return $totals;
    }

    private function logExport(Report $report, string $key, string $format): void
    {
        activity('reports')->event('report-exported')->withProperties(['report' => $key, 'format' => $format, 'personal_data' => $report->containsPersonalData()])->log('Report exported');
    }

    private function fileName(Report $report, ReportFilters $filters, string $extension): string
    {
        $range = $report->usesDates() ? '-'.$filters->from->toDateString().'-to-'.$filters->to->toDateString() : '';

        return Str::slug($report->title()).$range.'.'.$extension;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
