<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;


use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagCollection;
use App\Http\Resources\TagResource;



use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orgId = Auth::user()->organization_id;

        $tags = Tag::where('organization_id', $orgId)->get();

        return new TagCollection($tags);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagRequest $request)
    {
        $orgId = Auth::user()->organization_id;

        $Tag = new Tag();
        $Tag->name = $request->name;
        $Tag->organization_id = $orgId;
        $Tag->save();

        return response(
            new TagResource($Tag),
            Response::HTTP_CREATED
        );
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, Tag $Tag)
    {

        $Tag->name = $request->name;
        $Tag->save();

        return response(
            new TagResource($Tag),
            Response::HTTP_CREATED
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $Tag)
    {

        $Tag->delete();

        return response(null, 204);
    }
}
