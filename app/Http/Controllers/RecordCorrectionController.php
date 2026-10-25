<?php

namespace App\Http\Controllers;

use App\Actions\Corrections\GetCorrectionWorkspace;
use App\Actions\Corrections\RecordCorrection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecordCorrectionController extends Controller
{
    public function index(Request $request, GetCorrectionWorkspace $workspace): Response
    {
        $selection = $request->validate([
            'tab' => ['sometimes', Rule::in(['buy-ins', 'cashouts', 'payments', 'totals', 'missing'])],
            'game_id' => ['nullable', 'integer', 'exists:game_sessions,id'],
            'player_id' => ['nullable', 'integer', 'exists:players,id'],
            'mode' => ['sometimes', Rule::in(['missing_player', 'missing_visit', 'missing_buy_in', 'missing_cashout', 'missing_payment'])],
            'record_id' => ['nullable', 'integer'],
        ]);
        $selection['tab'] ??= 'buy-ins';
        if ($selection['tab'] === 'payments' || ($selection['tab'] === 'missing' && ($selection['mode'] ?? '') === 'missing_payment')) {
            unset($selection['game_id']);
        }

        return Inertia::render('corrections/Record', $workspace->get($selection) + [
            'indexUrl' => route('record-correction.index'), 'storeUrl' => route('record-correction.store'),
            'submissionId' => (string) Str::uuid(), 'today' => today()->toDateString(),
            'openRecordId' => $selection['record_id'] ?? null,
            'result' => $request->session()->get('correction_result'),
        ]);
    }

    public function store(Request $request, RecordCorrection $action): RedirectResponse
    {
        $entry = $action->record($request->user(), $request->all());
        $tab = match ($entry->kind) {
            'buy_in' => 'buy-ins', 'cashout' => 'cashouts', 'payment' => 'payments',
            'total_tips', 'total_cost' => 'totals', default => 'missing',
        };
        $selection = ['tab' => $tab, 'game_id' => $entry->game_session_id, 'player_id' => $entry->player_id];
        if ($tab === 'missing') {
            // A newly added participant now belongs in the existing-player selector.
            $selection['mode'] = $entry->kind === 'missing_player' ? 'missing_visit' : $entry->kind;
        }

        return to_route('record-correction.index', array_filter($selection, fn ($value) => $value !== null))
            ->with('correction_result', ['message' => 'Saved. The records and balances have been updated.', 'warnings' => $entry->warnings]);
    }
}
