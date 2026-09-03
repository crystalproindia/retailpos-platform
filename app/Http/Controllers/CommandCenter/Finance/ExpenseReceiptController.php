<?php

namespace App\Http\Controllers\CommandCenter\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\ExpenseTransaction;
use App\Services\Finance\ExpenseReceiptService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReceiptController extends Controller
{
    public function show(Request $request, ExpenseTransaction $expense, ExpenseReceiptService $receipts): StreamedResponse
    {
        return $receipts->response($expense, $request->user());
    }
}
