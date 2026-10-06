<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);                          // QT/2026-27/0001, shared by all revisions
            $table->unsignedSmallInteger('revision')->default(0);  // 0 = original, 1 = -R1 ...
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('site_id')->constrained();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->date('valid_till');
            $table->string('status', 20)->default('draft')->index();

            // Snapshots: an old quote must never change when masters change.
            $table->json('customer_snapshot');
            $table->string('place_of_supply', 2);
            $table->boolean('intra_state');
            $table->decimal('gst_pct', 5, 2);
            $table->decimal('min_item', 10, 2);
            $table->decimal('min_job', 10, 2);
            $table->decimal('advance_pct', 5, 2)->default(0);

            $table->string('discount_type', 10)->nullable(); // percent | flat
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('visit_charge', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('taxable_before_min', 12, 2)->default(0);
            $table->decimal('min_job_adjustment', 12, 2)->default(0);
            $table->decimal('taxable', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);

            $table->boolean('needs_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by', 20)->nullable();    // customer | staff
            $table->string('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['number', 'revision']);
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->json('product_snapshot'); // fields, constants, formulas, unit, base rate, minimum
            $table->string('measure_unit', 20);
            $table->string('name');
            $table->decimal('qty', 10, 2)->default(1);
            $table->json('inputs');

            // Selection ids are kept for re-editing; *_name / *_rate / *_factor are the snapshot.
            $table->foreignId('coating_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coating_name')->nullable();
            $table->decimal('coating_rate', 10, 2)->nullable();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('addon_name')->nullable();
            $table->decimal('addon_rate', 10, 2)->nullable();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_name')->nullable();
            $table->decimal('material_factor', 6, 3)->nullable();
            $table->foreignId('design_id')->nullable()->constrained()->nullOnDelete();
            $table->string('design_name')->nullable();
            $table->decimal('design_factor', 6, 3)->nullable();

            $table->decimal('area', 12, 4)->default(0);
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('raw_amount', 12, 2);
            $table->boolean('min_applied')->default(false);
            $table->decimal('amount', 12, 2);
            $table->string('area_text', 500)->nullable();
            $table->string('formula', 1000)->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
    }
};
