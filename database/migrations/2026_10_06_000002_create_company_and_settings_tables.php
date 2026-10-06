<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row tables.
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->text('address')->nullable();
            $table->string('state_code', 2);
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('terms')->nullable();
            $table->string('signatory')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('gst_pct', 5, 2)->default(18);
            $table->decimal('min_item', 10, 2)->default(0);
            $table->decimal('min_job', 10, 2)->default(0);
            $table->decimal('visit_charge', 10, 2)->default(0);
            $table->unsignedSmallInteger('valid_days')->default(15);
            // Maximum discount (% of subtotal) a role may give without admin approval.
            $table->decimal('discount_limit_sales', 5, 2)->default(10);
            $table->decimal('discount_limit_admin', 5, 2)->default(100);
            // Online payments.
            $table->boolean('allow_advance')->default(true);
            $table->decimal('advance_pct', 5, 2)->default(50);
            $table->boolean('allow_full_payment')->default(true);
            // Automation.
            $table->boolean('auto_job_on_payment')->default(true);
            $table->boolean('auto_invoice_on_done')->default(true);
            // Customer portal.
            $table->boolean('portal_enabled')->default(true);
            $table->text('estimate_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('company');
    }
};
