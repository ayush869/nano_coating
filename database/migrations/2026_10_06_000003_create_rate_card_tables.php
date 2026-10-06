<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Rate-card tables are never hard-deleted: old quotes reference them. */
    public function up(): void
    {
        $master = function (string $name, string $valueColumn, int $precision, int $scale) {
            Schema::create($name, function (Blueprint $table) use ($valueColumn, $precision, $scale) {
                $table->id();
                $table->string('name');
                $table->decimal($valueColumn, $precision, $scale);
                $table->string('description')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        };

        $master('coatings', 'rate_per_sqft', 10, 2);
        $master('addons', 'rate_per_sqft', 10, 2);
        $master('materials', 'factor', 6, 3);
        $master('designs', 'factor', 6, 3);

        // Optional restriction: if a coating has rows here it may only be used with these materials.
        Schema::create('coating_material', function (Blueprint $table) {
            $table->foreignId('coating_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->primary(['coating_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coating_material');
        foreach (['designs', 'materials', 'addons', 'coatings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
