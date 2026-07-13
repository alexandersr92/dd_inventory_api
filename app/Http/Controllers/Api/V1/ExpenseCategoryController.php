<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orgId = Auth::user()->organization_id;
        $categories = ExpenseCategory::where('organization_id', $orgId)->orderBy('name')->get();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $orgId = Auth::user()->organization_id;

        // Check if category exists for this org
        $exists = ExpenseCategory::where('organization_id', $orgId)
            ->where('name', $request->name)
            ->first();

        if ($exists) {
            return response()->json([
                'message' => 'La categoría ya existe.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $category = ExpenseCategory::create([
            'name' => $request->name,
            'organization_id' => $orgId,
        ]);

        return response()->json($category, Response::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $orgId = Auth::user()->organization_id;
        
        $category = ExpenseCategory::where('organization_id', $orgId)->findOrFail($id);

        $exists = ExpenseCategory::where('organization_id', $orgId)
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe otra categoría con ese nombre.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $category->update([
            'name' => $request->name,
        ]);

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $orgId = Auth::user()->organization_id;
        $category = ExpenseCategory::where('organization_id', $orgId)->findOrFail($id);

        // Optional: check if in use
        // if ($category->cashTransactions()->exists()) { ... }

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada con éxito.']);
    }
}
