<?php

namespace App\Http\Controllers;

use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CreateGameSession;
use App\Actions\Games\GetGameDetails;
use App\Actions\Games\RecordGameTotals;
use App\Http\Requests\Games\RecordGameTotalsRequest;
use App\Http\Requests\Games\StoreGameSessionRequest;
use App\Models\GameSession;
use App\Models\GameSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class GameSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $games = GameSession::query()
            ->orderByDesc('game_date')
            ->orderByDesc('game_number')
            ->get([
                'id',
                'game_date',
                'game_number',
                'game_name',
                'status',
                'min_buy_in',
                'max_buy_in',
            ]);

        return Inertia::render('games/Index', [
            'games' => $games,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CreateGameSession $action): Response
    {
        $gameDate = Carbon::today()->toDateString();
        $details = $action->nextGameDetails($gameDate);
        $settings = GameSetting::query()->findOrFail(1);

        return Inertia::render('games/Create', [
            'gameDate' => $gameDate,
            'gameName' => $details['game_name'],
            'defaultLimits' => [
                'min_buy_in' => $settings->default_min_buy_in,
                'max_buy_in' => $settings->default_max_buy_in,
            ],
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGameSessionRequest $request, CreateGameSession $action): RedirectResponse
    {
        $customLimits = $request->boolean('use_custom_limits')
            ? [
                'min_buy_in' => (int) $request->validated('min_buy_in'),
                'max_buy_in' => (int) $request->validated('max_buy_in'),
            ]
            : null;

        $game = $action->create(
            $request->validated('game_date'),
            $request->user(),
            $request->validated('game_name'),
            [
                'min_buy_in' => (int) $request->validated('confirmed_min_buy_in'),
                'max_buy_in' => (int) $request->validated('confirmed_max_buy_in'),
            ],
            $customLimits,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Game '.$game->game_name.' created successfully.',
        ]);

        return back();
    }

    public function editTotals(GameSession $gameSession): Response
    {
        return Inertia::render('games/Totals', [
            'game' => [
                'id' => $gameSession->id,
                'game_name' => $gameSession->game_name,
                'total_tips' => $gameSession->total_tips,
                'total_cost' => $gameSession->total_cost,
            ],
        ]);
    }

    public function recordTotals(
        RecordGameTotalsRequest $request,
        GameSession $gameSession,
        RecordGameTotals $action,
    ): RedirectResponse {
        $validated = $request->validated();

        $tips = $validated['total_tips'] ?? null;
        $cost = $validated['total_cost'] ?? null;

        $action->record(
            $gameSession,
            $request->user(),
            $tips === null ? null : (int) $tips,
            $cost === null ? null : (int) $cost,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Game totals recorded successfully.',
        ]);

        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(GameSession $gameSession, GetGameDetails $getGameDetails): Response
    {
        return Inertia::render('games/Show', [
            'game' => $getGameDetails->get($gameSession),
        ]);
    }

    public function close(
        Request $request,
        GameSession $gameSession,
        CloseGameSession $action,
    ): RedirectResponse {
        $game = $action->close($gameSession, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Game '.$game->game_name.' is closed.',
        ]);

        return to_route('games.show', $game);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return void
     */
    public function edit(GameSession $gameSession)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return void
     */
    public function update(Request $request, GameSession $gameSession)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return void
     */
    public function destroy(GameSession $gameSession)
    {
        //
    }
}
