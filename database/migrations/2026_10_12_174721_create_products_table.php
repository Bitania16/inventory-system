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
        $table->foreignId('category_id')->constrained()->restrictOnDelete();
        $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
        $table->string('product_code', 50)->unique();
        $table->string('name', 180);
        $table->string('slug', 200)->unique();
        $table->text('description')->nullable();
        $table->string('status', 20)->default('draft'); // draft, active, inactive, archived
        $table->boolean('track_inventory')->default(true);
        $table->boolean('is_sellable')->default(true);
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
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
