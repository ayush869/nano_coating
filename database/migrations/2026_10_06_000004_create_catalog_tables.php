<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Service categories shown on the website: "Sofa & upholstery", "Walls"...
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 16)->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Admin-defined products: their own inputs, constants and formulas.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('measure_unit', 20)->default('sq ft');
            $table->json('fields');
            $table->json('constants');
            $table->string('area_formula', 1000);
            $table->string('price_formula', 1000);
            $table->boolean('uses_coating')->default(true);
            $table->decimal('base_rate', 10, 2)->default(0);
            $table->foreignId('default_coating_id')->nullable()->constrained('coatings')->nullOnDelete();
            $table->foreignId('default_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('default_design_id')->nullable()->constrained('designs')->nullOnDelete();
            $table->boolean('apply_min')->default(true);
            $table->decimal('min_charge', 10, 2)->nullable(); // null = global minimum per item
            $table->boolean('customer_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Coatings a product may use. None = any coating.
        Schema::create('coating_product', function (Blueprint $table) {
            $table->foreignId('coating_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coating_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coating_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('services');
    }
};
