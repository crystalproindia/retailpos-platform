<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmPipelineAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class CrmAnalyticsExportController extends Controller
{
    public function __invoke(Request $request, CrmPipelineAnalyticsService $analytics, string $export)
    {
        abort_unless(in_array($export, ['pipeline', 'funnel', 'lost-reasons', 'salesperson-performance'], true), 404);
        $filters = $request->validate(['branch_id' => ['nullable', 'integer'], 'assigned_user_id' => ['nullable', 'integer'], 'source_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $user = $request->user();
        $rows = match ($export) {
            'pipeline' => $analytics->forecast($user, $filters)['rows']->map(fn (array $row) => ['Stage' => $row['stage'], 'Salesperson' => $row['salesperson'], 'Outlet' => $row['outlet'], 'Expected close period' => $row['close_period'], 'Open pipeline' => $row['open_value'], 'Weighted forecast' => $row['weighted_value']]),
            'funnel' => collect($analytics->summary($user, $filters)['stage_counts'])->map(fn ($count, $stage) => ['Stage' => str($stage)->replace('_', ' ')->headline()->toString(), 'Leads' => $count])->values(),
            'lost-reasons' => collect($analytics->summary($user, $filters)['lost_reasons'])->map(fn (array $row) => ['Lost reason' => $row['reason'], 'Leads' => $row['count']]),
            'salesperson-performance' => $analytics->salespersonPerformance($user, $filters)->map(fn (array $row) => ['Salesperson' => $row['salesperson'], 'Assigned leads' => $row['leads_assigned'], 'Won leads' => $row['won_leads'], 'Conversion rate' => $row['conversion_rate'], 'Actual sales' => $row['actual_sales_value'], 'Open pipeline' => $row['open_pipeline_value'], 'Weighted forecast' => $row['weighted_forecast'], 'Overdue follow-ups' => $row['follow_ups_overdue'], 'Stalled leads' => $row['stalled_leads']]),
        };

        return Response::streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) fputcsv($stream, array_keys($rows->first()));
            $rows->each(fn (array $row) => fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^[\t\r\n ]*[=+\-@]/', $value) ? "'".$value : $value, $row)));
            fclose($stream);
        }, "crm-analytics-{$export}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
