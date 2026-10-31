<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cash         = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney  = 'mobile_money';
    case Card         = 'card';
    case Cheque       = 'cheque';
    case Other        = 'other';
}