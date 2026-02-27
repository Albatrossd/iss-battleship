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
    Schema::create('bets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('round_id')->constrained()->cascadeOnDelete();
        $table->decimal('lat', 8, 5)->nullable();
        $table->decimal('lon', 8, 5)->nullable();
        $table->integer('row')->nullable();
        $table->integer('col')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
