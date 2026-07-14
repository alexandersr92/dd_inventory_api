<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use App\Models\LandingMedia;
use App\Models\LandingPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingAdminController extends Controller
{
    // --- 1. BIBLIOTECA DE IMÁGENES ---

    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $diskPath = $file->store('landing_media', 'public');
        $url = Storage::disk('public')->url($diskPath);

        $media = LandingMedia::create([
            'filename' => $filename,
            'disk_path' => $diskPath,
            'url' => $url,
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'media' => $media
        ], 201);
    }

    public function getMedia()
    {
        $media = LandingMedia::orderBy('created_at', 'desc')->get();
        return response()->json($media);
    }

    public function deleteMedia($id)
    {
        $media = LandingMedia::findOrFail($id);

        if (Storage::disk('public')->exists($media->disk_path)) {
            Storage::disk('public')->delete($media->disk_path);
        }

        $media->delete();

        return response()->json([
            'message' => 'Image deleted successfully.'
        ]);
    }

    // --- 2. CONTENIDO JSON DE LAS SECCIONES ---

    public function saveSectionContent(Request $request, $key)
    {
        $request->validate([
            'content' => 'required|array',
        ]);

        $section = LandingContent::updateOrCreate(
            ['section_key' => $key],
            ['content' => $request->content]
        );

        return response()->json([
            'message' => "Section '{$key}' updated successfully.",
            'section' => $section
        ]);
    }

    // --- 3. PLANES DE SUSCRIPCIÓN ---

    public function managePlan(Request $request, $id = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'period' => 'required|string|in:monthly,yearly,lifetime',
            'discount' => 'nullable|numeric|min:0|max:100',
            'features' => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive',
        ];

        $validated = $request->validate($rules);

        if ($id) {
            $plan = LandingPlan::findOrFail($id);
            $plan->update($validated);
            $message = 'Plan updated successfully.';
        } else {
            $plan = LandingPlan::create($validated);
            $message = 'Plan created successfully.';
        }

        return response()->json([
            'message' => $message,
            'plan' => $plan
        ]);
    }

    public function deletePlan($id)
    {
        $plan = LandingPlan::findOrFail($id);
        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted successfully.'
        ]);
    }
}
