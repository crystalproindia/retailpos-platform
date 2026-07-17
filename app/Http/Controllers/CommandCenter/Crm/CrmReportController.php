<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\Crm\CrmExecutiveReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class CrmReportController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('crm.reports.visualization');
    }

    public function visualization(Request $request, CrmExecutiveReportService $reports): View
    {
        return view('command-center.crm.reports.visualization', $this->payload($request, $reports));
    }

    public function executive(Request $request, CrmExecutiveReportService $reports): View
    {
        return view('command-center.crm.reports.visualization', $this->payload($request, $reports) + ['isExecutive' => true]);
    }

    public function show(Request $request, CrmExecutiveReportService $reports, string $report): View
    {
        abort_unless(in_array($report, ['sales', 'payments', 'onboarding', 'support', 'customers'], true), 404);

        return view('command-center.crm.reports.show', $this->payload($request, $reports, $report));
    }

    public function export(Request $request, CrmExecutiveReportService $reports, string $report)
    {
        abort_unless(in_array($report, ['sales', 'payments', 'support'], true), 404);
        $payload = $reports->report($request->user(), $report, $this->filters($request));
        $rows = match ($report) {
            'sales' => $payload['detail']['team']->map(fn ($row) => ['Sales person' => $row->label, 'Leads' => $row->leads, 'Pipeline value' => $row->value]),
            'payments' => $payload['detail']['pending_customers']->map(fn ($row) => ['Proforma' => $row->proforma_number, 'Customer' => $row->customer?->company_name ?? $row->customer_company ?? $row->customer_name, 'Balance' => $row->balance_amount, 'Due date' => $row->due_date?->format('Y-m-d')]),
            'support' => $payload['detail']['owners']->map(fn ($row) => ['Assigned staff' => $row->label, 'Tickets' => $row->value]),
        };

        return Response::streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            $headings = array_keys($rows->first() ?? []);
            if ($headings) fputcsv($stream, $headings);
            $rows->each(fn (array $row) => fputcsv($stream, $row));
            fclose($stream);
        }, "crm-{$report}-report-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, CrmExecutiveReportService $reports, ?string $report = null): array
    {
        $filters = $this->filters($request);

        return [
            'report' => $report,
            'data' => $report ? $reports->report($request->user(), $report, $filters) : $reports->dashboard($request->user(), $filters),
            'filterOptions' => [
                'users' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'sources' => CrmLeadSource::query()->where('company_id', $request->user()->company_id)->orderBy('sort_order')->get(['id', 'name']),
                'statuses' => CrmLeadStatus::query()->where('company_id', $request->user()->company_id)->orderBy('sort_order')->get(['id', 'name']),
                'customers' => CrmCustomer::query()->where('company_id', $request->user()->company_id)->orderBy('company_name')->get(['id', 'company_name']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate(['range' => ['nullable', 'in:this_month,last_month,last_3_months,last_6_months,this_year,custom'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'assigned_user_id' => ['nullable', 'integer'], 'source_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'status_id' => ['nullable', 'integer']]);
    }
}
