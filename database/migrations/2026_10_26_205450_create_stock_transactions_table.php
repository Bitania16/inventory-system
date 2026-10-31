<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->string('transaction_type', 30); // opening_balance, purchase_receipt, sale_issue, customer_return, supplier_return, adjustment_in, adjustment_out, transfer_out, transfer_in
            $table->string('status', 20)->default('draft'); // draft, posted, reversed
            $table->string('source_type', 100)->nullable(); // e.g. goods_receipt, sale
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversal_of_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};