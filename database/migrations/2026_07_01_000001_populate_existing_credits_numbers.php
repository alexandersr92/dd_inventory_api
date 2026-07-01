<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Credit;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $organizations = Credit::select('organization_id')->distinct()->get();

        foreach ($organizations as $org) {
            $credits = Credit::where('organization_id', $org->organization_id)
                ->whereNull('credit_number')
                ->orderBy('created_at', 'asc')
                ->get();

            $existingCount = Credit::where('organization_id', $org->organization_id)
                ->whereNotNull('credit_number')
                ->count();

            foreach ($credits as $index => $credit) {
                $num = $existingCount + $index + 1;
                $credit->credit_number = 'CR-' . str_pad($num, 6, '0', STR_PAD_LEFT);
                $credit->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No operation needed
    }
};
