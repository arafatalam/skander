<?php

namespace App\Http\Controllers;

use App\Actions\Players\DeactivatePlayer;
use App\Actions\Players\GetPlayerHistory;
use App\Actions\Players\ListPlayers;
use App\Http\Requests\Players\IndexPlayersRequest;
use App\Http\Requests\Players\StorePlayerRequest;
use App\Http\Requests\Players\UpdatePlayerRequest;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexPlayersRequest $request, ListPlayers $action): Response
    {
        $filters = $request->safe()->only(['search', 'owing']);

        return Inertia::render('players/Index', [
            'players' => $action->get($filters),
            'search' => $filters['search'] ?? '',
            'owing' => $filters['owing'] ?? 'all',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('players/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlayerRequest $request): RedirectResponse
    {
        Player::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Player created successfully.',
        ]);

        return back();
    }

    /**
     * Display the player's history page.
     */
    public function show(
        IndexPlayersRequest $request,
        Player $player,
        GetPlayerHistory $getPlayerHistory,
    ): Response {
        return Inertia::render(
            'players/Show',
            [
                ...$getPlayerHistory->get($player),
                'backUrl' => $request->validated('from') === 'dashboard'
                    ? route('dashboard') : route('players', $request->safe()->only(['search', 'owing', 'page'])),
                'backLabel' => $request->validated('from') === 'dashboard' ? 'Back to dashboard' : 'Back to players',
            ],
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Player $player): Response
    {
        return Inertia::render('players/Edit', [
            'player' => $player->only(['id', 'name', 'phone', 'email']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlayerRequest $request, Player $player): RedirectResponse
    {
        $player->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Player profile updated successfully.',
        ]);

        return to_route('players.show', $player);
    }

    /**
     * Deactivate a player while preserving their records.
     */
    public function deactivate(Request $request, Player $player, DeactivatePlayer $action): RedirectResponse
    {
        $action->deactivate($player, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Player deactivated successfully.',
        ]);

        return to_route('players.show', $player);
    }

    public function destroy(Player $player): void
    {
        //
    }
}
