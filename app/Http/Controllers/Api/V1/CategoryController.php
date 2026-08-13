<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;


use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;



use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    // SEGURIDAD: este controlador no autorizaba nada -> un usuario sin permisos
    // de producto podía borrar/renombrar toda la taxonomía. Reusa product.*.
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orgId = Auth::user()->organization_id;

        $categories = Category::where('organization_id', $orgId)->get();

        return new CategoryCollection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $this->authorize('create', Product::class);
        $orgId = Auth::user()->organization_id;

        $category = new Category();
        $category->name = $request->name;
        $category->organization_id = $orgId;
        $category->save();

        return response(
            new CategoryResource($category),
            Response::HTTP_CREATED
        );
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', Product::class);

        $category->name = $request->name;
        $category->save();

        return response(
            new CategoryResource($category),
            Response::HTTP_CREATED
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $this->authorize('delete', Product::class);

        $category->delete();

        return response(null, 204);
    }
}
