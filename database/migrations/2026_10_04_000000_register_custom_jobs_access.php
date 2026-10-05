<?php

use Database\Seeders\CustomJobsAccessSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CustomJobsAccessSeeder)->run();
    }

    public function down(): void
    {
        // Keep central catalog and permissions, as with movement permissions.
        // Rolling back code must not destroy account assignments or role history.
    }
};
