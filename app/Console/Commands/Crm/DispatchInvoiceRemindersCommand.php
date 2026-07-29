<?php

namespace App\Console\Commands\Crm;

use App\Repositories\Crm\InvoiceReminderRepository;
use App\Services\Crm\InvoiceReminderService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class DispatchInvoiceRemindersCommand extends Command
{
    protected $signature = 'invoices:dispatch-reminders
                            {--company= : Restrict the scan to one company ID}
                            {--dry-run : Report eligible reminders without creating deliveries}';

    protected $description = 'Queue eligible tenant sales invoice payment reminders.';

    public function handle(InvoiceReminderRepository $invoices, InvoiceReminderService $reminders): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['evaluated' => 0, 'queued' => 0, 'would_queue' => 0, 'skipped' => 0, 'failed' => 0];

        $invoices->automaticCandidates($companyId)
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($reminders, $dryRun, &$counts): void {
                foreach ($records as $invoice) {
                    $counts['evaluated']++;
                    $decision = $reminders->automaticEligibility($invoice);

                    if (! $decision['eligible']) {
                        $counts['skipped']++;

                        continue;
                    }

                    if ($dryRun) {
                        $counts['would_queue']++;

                        continue;
                    }

                    try {
                        $result = $reminders->queueAutomatic($invoice);
                        $result['queued'] ? $counts['queued']++ : $counts['skipped']++;
                    } catch (ValidationException) {
                        $counts['skipped']++;
                    } catch (Throwable) {
                        $counts['failed']++;
                    }
                }
            });

        $message = $dryRun
            ? "Reminder dry run: {$counts['evaluated']} evaluated, {$counts['would_queue']} eligible, {$counts['skipped']} skipped, {$counts['failed']} failed."
            : "Reminder dispatch: {$counts['evaluated']} evaluated, {$counts['queued']} queued, {$counts['skipped']} skipped, {$counts['failed']} failed.";
        $this->info($message);

        return self::SUCCESS;
    }
}
