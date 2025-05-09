<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreSettingRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingCollection;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
       protected SettingService $settings;

    public function __construct(SettingService $settings)
    {
        $this->settings = $settings;
    }
public function index(Request $request)
{
    $orgId = Auth::user()->organization_id;

    $type      = $request->type;
    $entityId  = $request->entity_id;
    $key       = $request->key;

    $query = Setting::where('organization_id', $orgId);

    if ($type) {
        $query->where('type', $type);
    }

    if ($entityId) {
        $query->where('entity_id', $entityId);
    }

    if ($key) {
        $setting = $query->where('key', $key);    
    }

    return new SettingCollection($query->get());
}

    public function store(StoreSettingRequest $request)
    {
        $orgId = auth()->user()->organization_id;
        $data = $request->validated();

        // Check if the setting already exists
        $existingSetting = Setting::where('organization_id', $orgId)
            ->where('type', $data['type'])
            ->where('entity_id', $data['entity_id'])
            ->where('key', $data['key'])
            ->where('value', $data['value'])
            ->first();
        if ($existingSetting) {
            return response()->json(['message' => 'Setting already exists'], 409);
        }

        //create the setting
        $setting = Setting::create([
            'organization_id' => $orgId,
            'type' => $data['type'],
            'entity_id' => $data['entity_id'],
            'key' => $data['key'],
            'value' => $data['value'],
        ]);
        //sync the settings

        return response()->json(['message' => 'Setting created', 'setting' => $setting], 201);
    }

    public function update(UpdateSettingRequest $request, Setting $setting)
    {
        $this->authorizeAccess($setting);
        $setting->update($request->validated());

        // Check if the setting already exists
        $existingSetting = Setting::where('organization_id', $setting->organization_id)
            ->where('type', $setting->type)
            ->where('entity_id', $setting->entity_id)
            ->where('key', $setting->key)
            ->where('value', $setting->value)
            ->first();
        if ($existingSetting) {
            return response()->json(['message' => 'Setting already exists'], 409);
        }

        return response()->json(['message' => 'Setting updated', 'setting' => $setting], 200);
    }

    public function destroy(Setting $setting)
    {
        $this->authorizeAccess($setting);
        $setting->delete();

        return response()->json(['message' => 'Setting deleted'], 200);
    }

    private function authorizeAccess(Setting $setting): void
    {
        if ($setting->organization_id !== auth()->user()->organization_id) {
            abort(403, 'No autorizado para modificar este setting.');
        }
    }
}
