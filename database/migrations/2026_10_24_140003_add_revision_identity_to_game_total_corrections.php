<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_total_corrections', function (Blueprint $table) {
            $table->unsignedInteger('revision')->nullable();
            $table->uuid('submission_id')->nullable();
        });

        $revisions = [];
        foreach (DB::table('game_total_corrections')->orderBy('corrected_at')->orderBy('id')->get() as $correction) {
            $key = $correction->game_session_id.':'.$correction->field;
            $revisions[$key] = ($revisions[$key] ?? 0) + 1;
            DB::table('game_total_corrections')->where('id', $correction->id)->update([
                'revision' => $revisions[$key],
                'submission_id' => (string) Str::uuid(),
            ]);
        }

        Schema::table('game_total_corrections', function (Blueprint $table) {
            $table->unsignedInteger('revision')->nullable(false)->change();
            $table->uuid('submission_id')->nullable(false)->change();
            $table->unique(['game_session_id', 'field', 'revision'], 'game_total_revision_unique');
            $table->unique('submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_total_corrections', function (Blueprint $table) {
            $table->dropUnique('game_total_revision_unique');
            $table->dropUnique(['submission_id']);
            $table->dropColumn(['revision', 'submission_id']);
        });
    }
};
