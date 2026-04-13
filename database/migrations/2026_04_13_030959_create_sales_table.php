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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->bigInteger('user_id');
            $table->string('sale_product');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->bigInteger('total_price');
            $table->bigInteger('total_payment');
            $table->bigInteger('change');
            $table->integer('used_point');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
