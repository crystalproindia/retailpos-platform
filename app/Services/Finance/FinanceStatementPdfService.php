<?php

namespace App\Services\Finance;

use App\Models\Crm\CrmCustomer;
use App\Models\Purchases\Supplier;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;
use Carbon\CarbonImmutable;

class FinanceStatementPdfService
{
    public function __construct(
        private readonly ReceivableService $receivables,
        private readonly PayableService $payables,
    ) {}

    public function customer(User $user, CrmCustomer $customer, CarbonImmutable $from, CarbonImmutable $to): DompdfDocument
    {
        return Pdf::loadView('pdf.finance-customer-statement', [
            'company' => $user->company,
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'statement' => $this->receivables->statement($user, $customer, $from, $to),
        ])->setPaper('a4');
    }

    public function supplier(User $user, Supplier $supplier, CarbonImmutable $from, CarbonImmutable $to): DompdfDocument
    {
        return Pdf::loadView('pdf.finance-supplier-statement', [
            'company' => $user->company,
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'statement' => $this->payables->statement($user, $supplier, $from, $to),
        ])->setPaper('a4');
    }
}
