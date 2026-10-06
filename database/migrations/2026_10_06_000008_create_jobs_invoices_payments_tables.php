<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named service_jobs because Laravel's queue already owns "jobs".
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->unique()->constrained();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_on')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled | in_progress | done
            $table->text('notes')->nullable();
            $table->json('photos')->nullable(); // after-work photos
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique(); // INV/2026-27/0001
            $table->foreignId('quote_id')->unique()->constrained();
            $table->foreignId('service_job_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique(); // RCPT/2026-27/0001, given when paid
            $table->foreignId('quote_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('purpose', 20);          // advance | full | balance | part
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20)->default('created')->index(); // created | paid | failed
            $table->string('gateway', 20);          // razorpay | test | cash | upi | bank | cheque
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('method', 30)->nullable(); // card, upi, netbanking...
            $table->string('reference')->nullable();  // offline reference / UTR
            $table->string('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('service_jobs');
    }
};
