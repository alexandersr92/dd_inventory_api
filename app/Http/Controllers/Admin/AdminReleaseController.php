<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductRelease;
use App\Services\AdminAudit;
use Illuminate\Http\Request;

class AdminReleaseController extends Controller
{
    public function index()
    {
        $releases = ProductRelease::orderByDesc('created_at')->get();
        return view('admin.releases.index', compact('releases'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'version' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'publish_now' => 'nullable|boolean',
        ]);

        $publishedAt = !empty($data['publish_now']) ? now() : null;

        $release = ProductRelease::create([
            'version' => trim($data['version']),
            'title' => trim($data['title']),
            'body' => $data['body'],
            'published_at' => $publishedAt,
        ]);

        AdminAudit::log('release.create', 'release', $release->id, "Versión {$release->version} creada");

        return redirect()->route('admin.releases.index')->with('success', 'Publicación creada exitosamente.');
    }

    public function update(Request $request, string $id)
    {
        $release = ProductRelease::findOrFail($id);

        $data = $request->validate([
            'version' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $release->update([
            'version' => trim($data['version']),
            'title' => trim($data['title']),
            'body' => $data['body'],
        ]);

        AdminAudit::log('release.update', 'release', $release->id, "Versión {$release->version} actualizada");

        return redirect()->route('admin.releases.index')->with('success', 'Publicación actualizada correctamente.');
    }

    public function togglePublish(string $id)
    {
        $release = ProductRelease::findOrFail($id);

        if ($release->published_at) {
            $release->update(['published_at' => null]);
            $action = 'release.unpublish';
            $msg = 'Publicación retirada (marcada como no publicada).';
        } else {
            $release->update(['published_at' => now()]);
            $action = 'release.publish';
            $msg = 'Publicación publicada con éxito.';
        }

        AdminAudit::log($action, 'release', $release->id, "Versión {$release->version} estado cambiado");

        return redirect()->route('admin.releases.index')->with('success', $msg);
    }

    public function destroy(string $id)
    {
        $release = ProductRelease::findOrFail($id);
        $version = $release->version;
        $release->delete();

        AdminAudit::log('release.delete', 'release', $id, "Versión {$version} eliminada");

        return redirect()->route('admin.releases.index')->with('success', 'Publicación eliminada.');
    }
}
