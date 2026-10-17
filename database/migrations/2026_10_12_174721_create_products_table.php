<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('sku')->unique(); // Stock Keeping Unit
        
        // Foreign Keys linking to your previous tables
        $table->foreignId('category_id')->constrained()->onDelete('cascade');
        $table->foreignId('brand_id')->constrained()->onDelete('cascade');
        $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
        
        $table->decimal('purchase_price', 10, 2);
        $table->decimal('selling_price', 10, 2);
        $table->integer('stock_quantity')->default(0); // Current stock level
        
        // Using an enum status similar to your schema design
        $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('active');
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
