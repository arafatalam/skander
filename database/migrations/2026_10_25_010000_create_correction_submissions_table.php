<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->string('kind');
            $table->string('request_hash', 64);
            $table->foreignId('game_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('before');
            $table->json('after');
            $table->json('warnings');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['kind', 'target_id']);
        });
        Schema::table('game_total_corrections', function (Blueprint $table) {
            $table->unsignedBigInteger('previous_amount')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('game_total_corrections')->whereNull('previous_amount')->exists()) {
            throw new RuntimeException('Cannot remove support for originally missing totals while their audit history exists.');
        }
        Schema::table('game_total_corrections', function (Blueprint $table) {
            $table->unsignedBigInteger('previous_amount')->nullable(false)->change();
        });
        Schema::dropIfExists('correction_submissions');
    }
};
