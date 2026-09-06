<?php

namespace App\Enums;

enum ReportType: string
{
    case INVOICES = 'invoices';
    case INVENTORY = 'inventory';
    case SALES = 'sales';
    case EXPENSES = 'expenses';
    case CREDITS = 'credits';
    case INVENTORY_MARGINS = 'inventory_margins';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
