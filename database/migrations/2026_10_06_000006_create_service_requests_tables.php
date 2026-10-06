<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer's request for a site visit, with a self-made estimate.
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique(); // SR/2026-27/0001
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('site_id')->constrained();
            $table->string('status', 20)->default('new')->index(); // new | assigned | quoted | closed | cancelled
            $table->string('source', 20)->default('portal');       // portal | staff
            $table->date('preferred_date')->nullable();
            $table->string('preferred_slot', 20)->nullable();      // morning | afternoon | evening
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();                    // general site photos
            $table->decimal('estimate_subtotal', 12, 2)->default(0);
            $table->decimal('estimate_total', 12, 2)->default(0);
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('visit_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('staff_notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('service_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->json('product_snapshot');
            $table->string('name')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->json('inputs');
            $table->foreignId('coating_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('design_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimate_area', 12, 4)->default(0);
            $table->decimal('estimate_amount', 12, 2)->default(0);
            $table->string('area_text')->nullable();
            $table->json('photos')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_items');
        Schema::dropIfExists('service_requests');
    }
};
