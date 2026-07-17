<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use App\Models\LandingMedia;
use App\Models\LandingPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminLandingController extends Controller
{
    public function index()
    {
        $media = LandingMedia::orderBy('created_at', 'desc')->get();
        
        // Obtener contenidos estructurados
        $hero = LandingContent::where('section_key', 'hero')->first()?->content ?? [
            'title' => 'DipleBill',
            'subtitle' => 'Facturación POS simple y rápida',
            'description' => 'La solución definitiva para la administración de tu negocio, inventario y facturación.',
            'cta_text' => 'Comenzar Gratis',
            'image_url' => ''
        ];
        
        $features = LandingContent::where('section_key', 'features')->first()?->content ?? [
            'title' => 'Funcionalidades avanzadas',
            'subtitle' => 'Todo lo que necesitas para crecer tu negocio',
            'items' => [
                ['icon' => 'zap', 'title' => 'Facturación POS', 'description' => 'Factura en segundos desde cualquier dispositivo.'],
                ['icon' => 'box', 'title' => 'Inventario Real', 'description' => 'Control de stock en tiempo real multitienda.'],
                ['icon' => 'users', 'title' => 'Clientes y Crédito', 'description' => 'Administra créditos, deudas y abonos de clientes.']
            ]
        ];

        $testimonials = LandingContent::where('section_key', 'testimonials')->first()?->content ?? [
            'title' => 'Lo que dicen nuestros clientes',
            'items' => [
                ['name' => 'Juan Pérez', 'role' => 'Dueño de Pulpería', 'comment' => 'DipleBill cambió la forma de facturar en mi negocio, ahora todo está ordenado.', 'stars' => 5]
            ]
        ];

        $faq = LandingContent::where('section_key', 'faq')->first()?->content ?? [
            'title' => 'Preguntas Frecuentes',
            'items' => [
                ['question' => '¿Tiene período de prueba?', 'answer' => 'Sí, puedes probar todas las funciones básicas gratis.'],
                ['question' => '¿Se conecta a impresoras térmicas?', 'answer' => 'Sí, la versión de escritorio soporta impresión térmica directa.']
            ]
        ];

        $footer = LandingContent::where('section_key', 'footer')->first()?->content ?? [
            'copyright' => '© ' . date('Y') . ' DipleBill. Todos los derechos reservados.',
            'address' => 'Estelí, Nicaragua',
            'phone' => '+505 1234 5678',
            'email' => 'soporte@diplebill.com'
        ];
        
        $plans = LandingPlan::orderBy('price', 'asc')->get();

        // Convertir todos los contenidos en JSON formateados
        $sections = [
            'hero' => json_encode($hero, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'features' => json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'testimonials' => json_encode($testimonials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'faq' => json_encode($faq, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'footer' => json_encode($footer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        return view('admin.landing.index', compact('media', 'hero', 'plans', 'sections'));
    }

    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $diskPath = $file->store('landing_media', 'public');
        $url = Storage::disk('public')->url($diskPath);

        LandingMedia::create([
            'filename' => $filename,
            'disk_path' => $diskPath,
            'url' => $url,
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return redirect()->back()->with('success', 'Imagen subida correctamente a la biblioteca.');
    }

    public function deleteMedia($id)
    {
        $media = LandingMedia::findOrFail($id);

        if (Storage::disk('public')->exists($media->disk_path)) {
            Storage::disk('public')->delete($media->disk_path);
        }

        $media->delete();

        return redirect()->back()->with('success', 'Imagen eliminada correctamente.');
    }

    public function updateContent(Request $request, $key)
    {
        $request->validate([
            'content_raw' => 'required|string',
        ]);

        $content = json_decode($request->content_raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->back()
                ->withErrors(['content_raw' => 'El JSON ingresado no es válido: ' . json_last_error_msg()])
                ->withInput();
        }

        LandingContent::updateOrCreate(
            ['section_key' => $key],
            ['content' => $content]
        );

        return redirect()->back()->with('success', "Sección '{$key}' actualizada correctamente.");
    }

    public function storePlan(Request $request, $id = null)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'period' => 'required|string|in:monthly,yearly,lifetime',
            'discount' => 'nullable|numeric|min:0|max:100',
            'features_raw' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        // Convertir string de features (separadas por salto de línea) en array
        $features = [];
        if ($request->filled('features_raw')) {
            $features = array_filter(array_map('trim', explode("\n", $request->features_raw)));
        }

        $data = [
            'name' => $request->name,
            'price' => $request->price,
            'period' => $request->period,
            'discount' => $request->discount ?? 0,
            'features' => array_values($features),
            'is_featured' => $request->has('is_featured'),
            'status' => $request->status ?? 'active',
        ];

        if ($id) {
            $plan = LandingPlan::findOrFail($id);
            $plan->update($data);
            $msg = 'Plan de suscripción actualizado correctamente.';
        } else {
            $plan = LandingPlan::create($data);
            $msg = 'Plan de suscripción creado correctamente.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deletePlan($id)
    {
        $plan = LandingPlan::findOrFail($id);
        $plan->delete();

        return redirect()->back()->with('success', 'Plan de precios eliminado correctamente.');
    }
}
