<?php
namespace App\Enums\Customers; enum LoyaltyTransactionType: string { case Earn='earn'; case Redeem='redeem'; case AdjustmentCredit='adjustment_credit'; case AdjustmentDebit='adjustment_debit'; case Expired='expired'; case Reversed='reversed'; }
