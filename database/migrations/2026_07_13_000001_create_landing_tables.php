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
        Schema::connection('central')->create('landing_media', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('disk_path');
            $table->text('url');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type');
            $table->timestamps();
        });

        Schema::connection('central')->create('landing_contents', function (Blueprint $table) {
            $table->id();
            $table->string('section_key')->unique();
            $table->json('content');
            $table->timestamps();
        });

        Schema::connection('central')->create('landing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('period'); // 'monthly', 'yearly', 'lifetime'
            $table->decimal('discount', 5, 2)->default(0.00); // percentage discount
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('active'); // 'active', 'inactive'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('landing_plans');
        Schema::connection('central')->dropIfExists('landing_contents');
        Schema::connection('central')->dropIfExists('landing_media');
    }
};
