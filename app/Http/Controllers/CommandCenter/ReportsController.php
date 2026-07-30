<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\Outlets\OutletAccessService;
use App\Services\Reports\RetailReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request, RetailReportingService $reports, OutletAccessService $outlets): View
    {
        return view('command-center.reports.index', $this->payload($request, $reports, $outlets));
    }

    public function show(Request $request, RetailReportingService $reports, OutletAccessService $outlets, string $report): View
    {
        return view('command-center.reports.show', $this->payload($request, $reports, $outlets) + ['report' => $reports->report($request->user(), $report, $this->filters($request))]);
    }

    public function export(Request $request, RetailReportingService $reports, string $report)
    {
        $data = $reports->report($request->user(), $report, $this->filters($request));
        $detail = collect($data['detail'])->except('notice', 'source', 'method');
        $rows = $detail->has('rows')
            ? collect($detail->get('rows'))->map(fn (array $row) => array_map(fn ($value) => is_int($value) ? number_format($value / 100, 2, '.', '') : (string) $value, $row))
            : $detail->map(fn ($value, $key) => ['Metric' => str($key)->replace('_', ' ')->headline()->toString(), 'Value' => is_int($value) ? number_format($value / 100, 2, '.', '') : (string) $value]);

        return Response::streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, array_keys($rows->first() ?? ['Metric' => null, 'Value' => null]));
            $rows->each(fn (array $row) => fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+\\-@]/', (string) $value) ? "'".$value : $value, $row)));
            fclose($stream);
        }, "retailpos-{$report}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function payload(Request $request, RetailReportingService $reports, OutletAccessService $outlets): array
    {
        return ['overview' => $reports->overview($request->user(), $this->filters($request)), 'outlets' => $outlets->accessibleOutlets($request->user()), 'canViewAllOutlets' => $outlets->hasCompanyWideAccess($request->user())];
    }

    private function filters(Request $request): array
    {
        return $request->validate(['outlet_id' => ['nullable', 'string'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
    }
}
