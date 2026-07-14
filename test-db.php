<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orgId = '471f9dc6-32ff-48e6-929e-bc42e8d60c9d';
$org = App\Models\Organization::find($orgId);
if ($org) {
    echo "ORGANIZATION: " . $org->name . "\n";
    $modules = $org->modules;
    echo "MODULES COUNT: " . $modules->count() . "\n";
    foreach ($modules as $m) {
        echo " - Module: " . $m->name . " | Slug: " . $m->slug . " | Pivot Status: " . $m->pivot->status . "\n";
    }
} else {
    echo "ORG NOT FOUND LOCALLY!\n";
}

echo "\nALL SYSTEM MODULES:\n";
foreach (App\Models\Module::all() as $m) {
    echo " - " . $m->name . " | Slug: " . $m->slug . " | Status: " . $m->status . " (ID: " . $m->id . ")\n";
}
