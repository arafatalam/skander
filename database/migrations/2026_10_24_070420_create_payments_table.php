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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount');

            $table->enum('direction', [
                'house_pays_player',
                'house_collects_from_player',
            ]);

            $table->date('paid_at');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['player_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
