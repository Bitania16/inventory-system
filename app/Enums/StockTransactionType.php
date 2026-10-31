<?php

namespace App\Enums;

enum StockTransactionType: string
{
    case OpeningBalance  = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
    case SaleIssue       = 'sale_issue';
    case CustomerReturn  = 'customer_return';
    case SupplierReturn  = 'supplier_return';
    case AdjustmentIn    = 'adjustment_in';
    case AdjustmentOut   = 'adjustment_out';
    case TransferOut     = 'transfer_out';
    case TransferIn      = 'transfer_in';
}