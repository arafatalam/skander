<?php

namespace App\Http\Controllers;

use App\Models\PlayerGame;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerGameController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PlayerGame $playerGame): Response
    {
        $playerGame->load(['player', 'gameSession']);

        $balance = $playerGame->opening_balance;
        $amount = '$'.number_format(abs($balance));

        if ($balance > 0) {
            $openingBalanceLabel = 'House owes player '.$amount;
        } elseif ($balance < 0) {
            $openingBalanceLabel = 'Player owes house '.$amount;
        } else {
            $openingBalanceLabel = 'Nothing owed';
        }

        return Inertia::render('player-games/Show', [
            'playerGame' => [
                'id' => $playerGame->id,
                'player_name' => $playerGame->player->name,
                'game_name' => $playerGame->gameSession->game_name,
                'opening_balance' => $playerGame->opening_balance,
                'opening_balance_label' => $openingBalanceLabel,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return void
     */
    public function edit(PlayerGame $playerGame)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return void
     */
    public function update(Request $request, PlayerGame $playerGame)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return void
     */
    public function destroy(PlayerGame $playerGame)
    {
        //
    }
}
