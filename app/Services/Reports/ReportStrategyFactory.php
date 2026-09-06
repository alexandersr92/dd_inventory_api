<?php

namespace App\Services\Reports;

use InvalidArgumentException;

class ReportStrategyFactory
{
    public static function make(string $type): BaseReportStrategy
    {
        switch ($type) {
            case 'invoices':
                return new InvoicesReportStrategy();
            case 'inventory':
                return new InventoryReportStrategy();
            case 'sales':
                return new SalesReportStrategy();
            case 'expenses':
                return new ExpensesReportStrategy();
            case 'credits':
                return new CreditsReportStrategy();
            case 'inventory_margins':
                return new InventoryMarginsReportStrategy();
            default:
                throw new InvalidArgumentException("Report type [{$type}] is not supported.");
        }
    }
}
