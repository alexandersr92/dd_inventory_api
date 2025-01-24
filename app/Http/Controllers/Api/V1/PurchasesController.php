<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
         // Validar que el archivo sea Excel o CSV
         $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        // Leer y procesar el archivo
        $file = $request->file('file');
        $import = new InventoryImport();
        Excel::import($import, $file);

        // Obtener resultados
        $validRecords = $import->getValidRecords();
        $invalidRecords = $import->getInvalidRecords();

        return response()->json([
            'valid_records' => $validRecords,
            'invalid_records' => $invalidRecords,
        ]);
    }
}
