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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('print_logo')->nullable();
            $table->string('print_header')->nullable();
            $table->string('print_footer')->nullable();
            $table->string('print_note')->nullable();
            $table->string('print_width')->nullable();
            $table->integer('invoice_number')->default(0);
            $table->string('invoice_prefix')->default('');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('print_logo');
            $table->dropColumn('print_header');
            $table->dropColumn('print_footer');
            $table->dropColumn('print_note');
            $table->dropColumn('print_width');
            $table->dropColumn('invoice_number');
            $table->dropColumn('invoice_prefix');
        });
    }
};
