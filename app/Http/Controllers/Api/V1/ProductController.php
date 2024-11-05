<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\InventoryDetail;

use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;



class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $per_page = $request->query('per_page', 20);

        $products = Product::where('organization_id', $orgId)->orderBy('name')->paginate($per_page);

        if ($request->has('search')) {
            $search = $request->query('search');
            $search_by = $request->query('search_by', 'name');

            $products = Product::where($search_by, 'like', '%' . $search . '%')
                ->where('organization_id', $orgId)
                ->paginate($per_page);
        }

        if ($request->has('sort')) {
            $sortBy = $request->query('sort', 'name');
            $order = $request->query('order', 'asc');

            $products = Product::orderBy($sortBy, $order)
                ->where('organization_id', $orgId)
                ->paginate($per_page);
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);

            $products = Product::whereHas('tags', function ($query) use ($tags) {
                $query->whereIn('tags.id', $tags);
            })->where('organization_id', $orgId)
                ->paginate($per_page);
        }

        if ($request->has('categories')) {
            $categories = explode(',', $request->categories);

            $products = Product::whereHas('categories', function ($query) use ($categories) {
                $query->whereIn('categories.id', $categories);
            })->where('organization_id', $orgId)
                ->paginate($per_page);
        }


        return new ProductCollection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $orgId = Auth::user()->organization_id;

        $request->merge(['organization_id' => $orgId]);

        //sku debe ser unico dentro de la organizacion
        $request->validate([
            'sku' => 'unique:products,sku,NULL,id,organization_id,' . $orgId,
            'barcode' => 'unique:products,barcode,NULL,id,organization_id,' . $orgId,
        ]);

        $product = Product::create($request->all());

        if ($request->has('categories')) {
            $categories = explode(',', $request->categories);

            foreach ($categories as $category) {
                // Asegúrate de que la categoría exista antes de adjuntarla
                if (Category::find($category)) {
                    $product->categories()->attach($category);
                }
            }
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);

            foreach ($tags as $tag) {
                // Asegúrate de que la etiqueta exista antes de adjuntarla
                if (Tag::find($tag)) {
                    $product->tags()->attach($tag);
                }
            }
        }

        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('productsImages', 'public');
        }

        if ($request->has('suppliers')) {
            $suppliers = explode(',', $request->suppliers);

            foreach ($suppliers as $supplier) {
                // Asegúrate de que el proveedor exista antes de adjuntarlo
                if (Supplier::find($supplier)) {
                    $product->suppliers()->attach($supplier);
                }
            }
        }

        if ($request->has('inventories')) {

            $inventories = explode(',', $request->inventories);


            foreach ($inventories as $inventory) {

                if (Inventory::find($inventory)) {
                    //add product to inventory whit quantity 0

                    $inventoryDetail = new InventoryDetail();
                    $inventoryDetail->inventory_id = $inventory;

                    $inventoryDetail->product_id = $product->id;

                    $inventoryDetail->quantity = 0;
                    $inventoryDetail->price = $product->price;
                    $inventoryDetail->save();
                }
            }
        }

        $product->save();

        return response(
            new ProductResource($product),
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {

        return response(
            new ProductResource($product),
            Response::HTTP_OK
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {

        $data = $request->all();

        $orgId = Auth::user()->organization_id;

        $request->validate([
            'sku' => 'unique:products,sku,' . $product->id . ',id,organization_id,' . $orgId,
            'barcode' => 'unique:products,barcode,' . $product->id . ',id,organization_id,' . $orgId,
        ]);


        // Actualizar producto
        $product->update($data);


        if ($request->has('categories')) {
            $categories = explode(',', $request->categories);

            $product->categories()->detach();

            foreach ($categories as $category) {
                // Asegúrate de que la categoría exista antes de adjuntarla
                if (Category::find($category)) {
                    $product->categories()->attach($category);
                }
            }
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);

            $product->tags()->detach();

            foreach ($tags as $tag) {
                // Asegúrate de que la etiqueta exista antes de adjuntarla
                if (Tag::find($tag)) {
                    $product->tags()->attach($tag);
                }
            }
        }

        if ($request->has('suppliers')) {
            $suppliers = explode(',', $request->suppliers);

            $product->suppliers()->detach();

            foreach ($suppliers as $supplier) {
                // Asegúrate de que el proveedor exista antes de adjuntarlo
                if (Supplier::find($supplier)) {
                    $product->suppliers()->attach($supplier);
                }
            }
        }


        return response(
            new ProductResource($product),
            Response::HTTP_OK
        );
    }

    public function removeImage(Product $product)
    {
        $product->image = null;
        $product->save();

        return response(
            new ProductResource($product),
            Response::HTTP_OK
        );
    }

    public function addImageToProduct(Request $request, Product $product)
    {


        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('productsImages', 'public');
        }
        $product->save();

        return response(
            new ProductResource($product),
            Response::HTTP_OK
        );
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response(
            null,
            Response::HTTP_NO_CONTENT
        );
    }
}
