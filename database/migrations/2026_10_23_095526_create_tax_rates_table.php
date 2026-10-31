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
       Schema::create('tax_rates', function (Blueprint $table) {
    $table->id();
    $table->string('code', 40)->unique();
    $table->string('name', 120);
    $table->decimal('rate_percent', 7, 4);
    $table->boolean('is_inclusive')->default(false);
    $table->boolean('is_active')->default(true);
    $table->date('effective_from')->nullable();
    $table->date('effective_to')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
