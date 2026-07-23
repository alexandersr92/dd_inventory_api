<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/**
 * CRUD de los planes funcionales (tabla central.plans) que se asignan a las
 * organizaciones y definen sus límites. Distinto de LandingPlan (marketing).
 */
class AdminPlanController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('price_monthly')->get();
        return view('admin.plans.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        Plan::create($data);
        \App\Services\AdminAudit::log('plan.create', 'plan', $data['slug'], "Plan {$data['name']} creado");

        return redirect()->route('admin.plans.index')->with('success', 'Plan creado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);
        $data = $this->validated($request, $plan->id);
        $plan->update($data);
        \App\Services\AdminAudit::log('plan.update', 'plan', $plan->slug, "Plan {$plan->name} actualizado");

        return redirect()->route('admin.plans.index')->with('success', 'Plan actualizado correctamente.');
    }

    public function destroy($id)
    {
        $plan = Plan::findOrFail($id);

        if ($plan->organizations()->exists()) {
            return redirect()->route('admin.plans.index')
                ->withErrors(['error' => 'No se puede eliminar: hay organizaciones con este plan asignado.']);
        }

        $slug = $plan->slug;
        $plan->delete();
        \App\Services\AdminAudit::log('plan.delete', 'plan', $slug, "Plan {$slug} eliminado");

        return redirect()->route('admin.plans.index')->with('success', 'Plan eliminado.');
    }

    private function validated(Request $request, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'max_sellers' => 'nullable|integer|min:0',
            'max_stores' => 'nullable|integer|min:0',
            'max_monthly_invoices' => 'nullable|integer|min:0',
            'tenancy_type' => 'required|in:shared,dedicated',
            'price_monthly' => 'required|numeric|min:0',
            'price_annual' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        // Los checkboxes no envían nada cuando están desmarcados.
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }
}
