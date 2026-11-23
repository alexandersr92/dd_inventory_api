<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);
        $orgId = Auth::user()->organization_id;

        if ($request->boolean('tree')) {
            $roots = Category::where('organization_id', $orgId)
                ->whereNull('parent_id')
                ->with(['allChildren' => function ($q) {
                    $q->withCount(['products', 'children']);
                }])
                ->withCount(['products', 'children'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            return CategoryResource::collection($roots);
        }

        $query = Category::where('organization_id', $orgId)
            ->withCount(['products', 'children']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null' || $request->parent_id === null || $request->parent_id === '') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        $categories = $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();

        return CategoryResource::collection($categories);
    }

    public function tree()
    {
        $this->authorize('viewAny', Category::class);
        $orgId = Auth::user()->organization_id;

        $roots = Category::where('organization_id', $orgId)
            ->whereNull('parent_id')
            ->with(['allChildren' => function ($q) {
                $q->withCount(['products', 'children']);
            }])
            ->withCount(['products', 'children'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return CategoryResource::collection($roots);
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->authorize('create', Category::class);
        $orgId = Auth::user()->organization_id;

        $parentId = $request->filled('parent_id') ? $request->parent_id : null;
        $name = trim($request->name);

        // Check uniqueness within the same parent & organization
        $existingQuery = Category::where('organization_id', $orgId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);

        if ($parentId) {
            $existingQuery->where('parent_id', $parentId);
        } else {
            $existingQuery->whereNull('parent_id');
        }

        if ($existingQuery->exists()) {
            return response()->json([
                'message' => 'Ya existe una categoría con ese nombre en este nivel.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $level = 1;
        if ($parentId) {
            $parent = Category::where('organization_id', $orgId)->find($parentId);
            if ($parent) {
                $level = $parent->level + 1;
            }
        }

        $category = new Category();
        $category->organization_id = $orgId;
        $category->name = $name;
        $category->slug = Str::slug($name);
        $category->parent_id = $parentId;
        $category->level = $level;
        $category->sort_order = $request->input('sort_order', 0);
        $category->status = $request->input('status', 'active');
        $category->save();

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category)
    {
        $this->authorize('view', Category::class);
        $category->loadCount(['products', 'children']);
        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', Category::class);
        $orgId = Auth::user()->organization_id;

        $name = $request->has('name') ? trim($request->name) : $category->name;
        $parentId = $request->has('parent_id') ? ($request->filled('parent_id') ? $request->parent_id : null) : $category->parent_id;

        // Prevent cycle: parent cannot be self or any descendant
        if ($parentId) {
            if ($parentId === $category->id) {
                return response()->json([
                    'message' => 'Una categoría no puede ser su propio padre.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($this->isDescendant($category, $parentId)) {
                return response()->json([
                    'message' => 'No puedes asignar como padre a una de sus propias subcategorías.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Check duplicate name under same parent
        $existingQuery = Category::where('organization_id', $orgId)
            ->where('id', '!=', $category->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);

        if ($parentId) {
            $existingQuery->where('parent_id', $parentId);
        } else {
            $existingQuery->whereNull('parent_id');
        }

        if ($existingQuery->exists()) {
            return response()->json([
                'message' => 'Ya existe una categoría con ese nombre en este nivel.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $level = 1;
        if ($parentId) {
            $parent = Category::where('organization_id', $orgId)->find($parentId);
            if ($parent) {
                $level = $parent->level + 1;
            }
        }

        $category->name = $name;
        $category->slug = Str::slug($name);
        $category->parent_id = $parentId;
        $category->level = $level;
        if ($request->has('sort_order')) {
            $category->sort_order = $request->sort_order;
        }
        if ($request->has('status')) {
            $category->status = $request->status;
        }
        $category->save();

        // Recursively update levels of all descendants if parent changed
        $this->updateDescendantLevels($category);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', Category::class);

        // Check children
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque contiene subcategorías asociadas. Elimine o reasigne las subcategorías primero.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check products
        if ($category->products()->exists() || $category->legacyProducts()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asignados. Reasigne los productos primero.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->delete();

        return response(null, 204);
    }

    private function isDescendant(Category $category, string $targetParentId): bool
    {
        $children = $category->children;
        foreach ($children as $child) {
            if ($child->id === $targetParentId) {
                return true;
            }
            if ($this->isDescendant($child, $targetParentId)) {
                return true;
            }
        }
        return false;
    }

    private function updateDescendantLevels(Category $category): void
    {
        $children = Category::withoutGlobalScopes()->where('parent_id', $category->id)->get();
        foreach ($children as $child) {
            $child->level = $category->level + 1;
            $child->save();
            $this->updateDescendantLevels($child);
        }
    }
}
