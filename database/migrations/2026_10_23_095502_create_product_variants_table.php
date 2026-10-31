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
        Schema::create('product_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('sku', 80)->unique();
    $table->string('barcode', 100)->nullable()->unique();
    $table->string('variant_name', 120)->nullable();
    $table->json('attributes')->nullable();
    $table->decimal('cost_price', 14, 2)->default(0);
    $table->decimal('selling_price', 14, 2)->default(0);
    $table->decimal('wholesale_price', 14, 2)->nullable();
    $table->decimal('minimum_selling_price', 14, 2)->nullable();
    $table->decimal('weight', 12, 3)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
