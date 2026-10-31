<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_code', 40)->unique();
            $table->string('customer_type', 30)->default('individual'); // individual, business
            $table->string('display_name', 180);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedInteger('default_credit_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};