<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Credit;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop the global unique index
        Schema::table('credits', function (Blueprint $table) {
            $table->dropUnique('credits_credit_number_unique');
        });

        // 2. Populate sequential credit numbers per organization
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

        // 3. Add compound unique index per organization to enforce tenant scope
        Schema::table('credits', function (Blueprint $table) {
            $table->unique(['organization_id', 'credit_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'credit_number']);
            $table->unique('credit_number');
        });
    }
};
