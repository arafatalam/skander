<?php

namespace App\Http\Controllers;

use App\Actions\Games\CreateGameSession;
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

    /**
     * Display the specified resource.
     */
    public function show(GameSession $gameSession): Response
    {
        return Inertia::render('games/Show', [
            'game' => [
                'id' => $gameSession->id,
                'game_date' => $gameSession->game_date->format('Y-m-d'),
                'game_name' => $gameSession->game_name,
                'status' => $gameSession->status,
            ],
        ]);
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
