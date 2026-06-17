<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('invoicing.table_names.payments', 'payments');
        $invoicesTable = config('invoicing.table_names.invoices', 'invoices');

        Schema::create($tableName, function (Blueprint $table) use ($invoicesTable) {
            $table->id();
            $table->foreignId('invoice_id')->constrained($invoicesTable)->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('gateway');
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default(config('invoicing.currency', 'USD'));
            $table->string('status')->default('pending');
            $table->json('gateway_response')->nullable();

            // Manual verification fields (used by bank_transfer gateway)
            $table->string('proof_file')->nullable();
            $table->text('proof_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->index();

            $table->timestamps();

            $table->index(['gateway', 'transaction_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $tableName = config('invoicing.table_names.payments', 'payments');
        Schema::dropIfExists($tableName);
    }
};
