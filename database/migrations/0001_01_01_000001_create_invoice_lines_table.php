<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('invoicing.table_names.invoice_lines', 'invoice_lines');
        $invoicesTable = config('invoicing.table_names.invoices', 'invoices');

        Schema::create($tableName, function (Blueprint $table) use ($invoicesTable) {
            $table->id();
            $table->foreignId('invoice_id')->constrained($invoicesTable)->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tableName = config('invoicing.table_names.invoice_lines', 'invoice_lines');
        Schema::dropIfExists($tableName);
    }
};
