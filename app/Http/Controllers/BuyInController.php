<?php

namespace App\Http\Controllers;

use App\Actions\Games\CreateBuyIn;
use App\Http\Requests\BuyIns\StoreBuyInRequest;
use App\Models\BuyIn;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyInController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $games = GameSession::query()
            ->where('status', 'open')
            ->orderByDesc('game_date')
            ->orderByDesc('game_number')
            ->get([
                'id',
                'game_name',
                'min_buy_in',
                'max_buy_in',
            ]);

        $players = Player::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return Inertia::render('buy-ins/Create', [
            'games' => $games,
            'players' => $players,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBuyInRequest $request, CreateBuyIn $action): RedirectResponse
    {
        $game = GameSession::query()
            ->findOrFail($request->validated('game_session_id'));

        $player = Player::query()
            ->findOrFail($request->validated('player_id'));

        $participant = $action->create(
            $game,
            $player,
            $request->user(),
            (int) $request->validated('cash_amount'),
            (int) $request->validated('credit_amount'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Buy-in recorded successfully.',
        ]);

        return to_route('player-games.show', [
            'playerGame' => $participant->id,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(BuyIn $buyIn)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BuyIn $buyIn)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BuyIn $buyIn)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BuyIn $buyIn)
    {
        //
    }
}
