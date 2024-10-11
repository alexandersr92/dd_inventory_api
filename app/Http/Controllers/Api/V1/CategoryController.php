<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;


use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;



use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
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

        $category->delete();

        return response(null, 204);
    }
}
