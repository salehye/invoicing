<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('invoicing.table_names.invoices', 'invoices');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('billable');
            $table->foreignId('user_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('currency', 3)->default(config('invoicing.currency', 'USD'));
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'billable_type', 'billable_id']);
        });
    }

    public function down(): void
    {
        $tableName = config('invoicing.table_names.invoices', 'invoices');
        Schema::dropIfExists($tableName);
    }
};
