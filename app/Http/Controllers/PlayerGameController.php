<?php

namespace App\Http\Controllers;

use App\Actions\Players\CalculatePlayerBalance;
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
    public function show(PlayerGame $playerGame, CalculatePlayerBalance $calculatePlayerBalance): Response
    {
        $playerGame->load(['player', 'gameSession']);

        $gameBalance = $calculatePlayerBalance->calculate($playerGame->player, $playerGame->gameSession->game_date->toDateString());

        return Inertia::render('player-games/Show', [
            'playerGame' => [
                'id' => $playerGame->id,
                'player_name' => $playerGame->player->name,
                'game_name' => $playerGame->gameSession->game_name,
                'daily_balance_label' => $this->balanceLabel($gameBalance),
            ],
        ]);
    }

    private function balanceLabel(int $balance): string
    {
        $amount = '$'.number_format(abs($balance));

        if ($balance > 0) {
            return 'House owes player '.$amount;
        }

        if ($balance < 0) {
            return 'Player owes house '.$amount;
        }

        return 'Nothing owed';
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
