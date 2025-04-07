<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryExport implements FromCollection, WithHeadings
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function collection()
    {
        return collect($this->products)->map(function ($product) {
            return [
                $product['product_name'] ?? '',
                $product['quantity'] ?? '',
                $product['status'] ?? '',
                $product['price'] ?? '',
                $product['barcode'] ?? '',
                $product['sku'] ?? '',
                $product['tags'] ?? '',
                $product['category'] ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Product Name',
            'Quantity',
            'Status',
            'Price',
            'Barcode',
            'SKU',
            'Tags',
            'Category',
        ];
    }
}