<?php
namespace App\Enums\Customers; enum WalletTransactionType: string { case Credit='credit'; case Debit='debit'; case RefundCreditFuture='refund_credit_future'; case AdjustmentCredit='adjustment_credit'; case AdjustmentDebit='adjustment_debit'; case PaymentFuture='payment_future'; }
