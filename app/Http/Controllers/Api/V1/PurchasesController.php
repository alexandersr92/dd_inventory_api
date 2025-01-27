<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ChunkImport;
use App\Models\Product;
use Illuminate\Support\Collection; 

class PurchasesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $orgId = Auth::user()->organization_id;
        $userID = Auth::user()->id;
    }

    /**
     * Display the specified resource.
     */
    public function show(Purchases $purchases)
    {
        //
    }

 

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchases $purchases)
    {
        //
    }
    
    public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx',
    ]);

    $file = $request->file('file');

    // Arrays para los resultados
    $validExisting = [];
    $validNew = [];
    $invalid = [];

    // Leer archivo en chunks para evitar problemas de memoria
    Excel::import(new class($validExisting, $validNew, $invalid) implements \Maatwebsite\Excel\Concerns\ToCollection {
        private $validExisting;
        private $validNew;
        private $invalid;

        public function __construct(&$validExisting, &$validNew, &$invalid)
        {
            $this->validExisting = &$validExisting;
            $this->validNew = &$validNew;
            $this->invalid = &$invalid;
        }

        public function collection(Collection $rows)
        {
            foreach ($rows->skip(1) as $index => $row) { // Saltar encabezados
                $sku = trim($row[0]);
                $price = trim($row[5]);
                $quantity = trim($row[6]);
                $cost = trim($row[7]);

                $errors = [];

                // Validaciones
                if (empty($sku)) {
                    $errors[] = 'El SKU está vacío';
                }

                if (empty($price) || !is_numeric(str_replace('C$', '', $price))) {
                    $errors[] = 'El precio no es válido';
                }

                if (empty($quantity) || !is_numeric($quantity)) {
                    $errors[] = 'La cantidad no es válida';
                }

                if (empty($cost) || !is_numeric(str_replace('C$', '', $cost))) {
                    $errors[] = 'El costo no es válido';
                }

                // Verificar si existe el SKU en la base de datos
                $exists = Product::where('sku', $sku)->exists();

                if (empty($errors)) {
                    // Datos válidos
                    if ($exists) {
                        $this->validExisting[] = [
                            'sku' => $sku,
                            'price' => (float)str_replace('C$', '', $price),
                            'quantity' => (int)$quantity,
                            'cost' => (float)str_replace('C$', '', $cost),
                        ];
                    } else {
                        $this->validNew[] = [
                            'sku' => $sku,
                            'name' => trim($row[3]),
                            'barcode' => trim($row[4]),
                            'price' => (float)str_replace('C$', '', $price),
                            'quantity' => (int)$quantity,
                            'cost' => (float)str_replace('C$', '', $cost),
                        ];
                    }
                } else {
                    // Datos inválidos
                    $this->invalid[] = [
                        'row' => $index + 2, // Fila actual (sumar 2 porque se salta encabezado)
                        'errors' => $errors,
                         'data' => [
                            'sku' => $sku,
                            'name' => trim($row[3]),
                            'barcode' => trim($row[4]),
                            'price' => $price,
                            'quantity' => $quantity,
                            'cost' => $cost,
                        ],
                    ];
                }
            }
        }
    }, $file);

    return response()->json([
        'valid_existing' => $validExisting,
        'valid_new' => $validNew,
        'invalid' => $invalid,
    ]);
}
}
