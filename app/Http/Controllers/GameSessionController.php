<?php

namespace App\Http\Controllers;

use App\Actions\Games\CreateGameSession;
use App\Http\Requests\Games\StoreGameSessionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class GameSessionController extends Controller
{
    public function create(CreateGameSession $action): Response
    {
        $gameDate = Carbon::today()->toDateString();
        $details = $action->nextGameDetails($gameDate);

        return Inertia::render('games/Create', [
            'gameDate' => $gameDate,
            'gameName' => $details['game_name'],
        ]);

    }

    public function store(StoreGameSessionRequest $request, CreateGameSession $action): RedirectResponse
    {
        $game = $action->create(
            $request->validated('game_date'),
            $request->user(),
            $request->validated('game_name'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Game '.$game->game_name.' created successfully.',
        ]);

        return back();
    }
}
