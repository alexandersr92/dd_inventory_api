<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sellers = \App\Models\Seller::onlyTrashed()->get();
        foreach ($sellers as $seller) {
            if (!str_contains($seller->code, '_DEL_')) {
                // Limit code size to fit max length 50 or 8 if it's strict, but code is varchar(255) in DB.
                // Let's use _DEL_ + timestamp
                $seller->code = substr($seller->code, 0, 10) . '_DEL_' . time();
                $seller->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
