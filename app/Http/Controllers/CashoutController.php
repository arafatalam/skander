<?php

namespace App\Http\Controllers;

use App\Actions\Games\CorrectCashout;
use App\Actions\Games\CreateCashout;
use App\Http\Requests\Cashouts\StoreCashoutRequest;
use App\Models\Cashout;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CashoutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): void
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $participants = $this->getParticipantsReadyToCashOut()
            ->map(fn (PlayerGame $participant) => [
                'game_session_id' => $participant->game_session_id,
                'game_name' => $participant->gameSession->game_name,
                'player_id' => $participant->player_id,
                'player_name' => $participant->player->name,
                'expected_visit_id' => $participant->latestVisit->id,
                'visit_number' => $participant->latestVisit->visit_number,
            ]);

        return Inertia::render('cashouts/Create', [
            'games' => $this->getOpenGames(),
            'participants' => $participants,
        ]);
    }

    /**
     * @return Collection<int, GameSession>
     */
    private function getOpenGames(): Collection
    {
        return GameSession::query()
            ->where('status', 'open')
            ->orderByDesc('game_date')
            ->orderByDesc('game_number')
            ->get(['id', 'game_name']);
    }

    /**
     * @return Collection<int, PlayerGame>
     */
    private function getParticipantsReadyToCashOut(): Collection
    {
        return PlayerGame::query()
            ->whereHas('gameSession', function ($query) {
                $query->where('status', 'open');
            })
            ->whereHas('latestVisit', function ($query) {
                $query->has('buyIns')
                    ->doesntHave('cashout');
            })
            ->with(['player', 'gameSession', 'latestVisit'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCashoutRequest $request, CreateCashout $action): RedirectResponse
    {

        $game = GameSession::query()
            ->findOrFail($request->integer('game_session_id'));

        $player = Player::query()
            ->findOrFail($request->integer('player_id'));

        $cashout = $action->create(
            $game,
            $player,
            $request->user(),
            $request->integer('ending_chips'),
            $request->integer('cash_paid'),
            $request->integer('expected_visit_id'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cashout recorded successfully.',
        ]);

        return to_route('player-games.show', [
            'playerGame' => $cashout->playerGameVisit->player_game_id,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cashout $cashout): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cashout $cashout): Response
    {
        $cashout->load('playerGameVisit.playerGame.player', 'playerGameVisit.playerGame.gameSession');

        $visit = $cashout->playerGameVisit;
        $participant = $visit->playerGame;

        return Inertia::render('cashouts/Edit', [
            'cashout' => $cashout->only([
                'id',
                'ending_chips',
                'cash_paid',
                'creation_method',
            ]) + [
                'player_name' => $participant->player->name,
                'game_name' => $participant->gameSession->game_name,
                'visit_number' => $visit->visit_number,
                'revision' => (int) ($cashout->corrections()->max('revision') ?? 0),
            ],
            'submissionId' => (string) Str::uuid(),
            'updateUrl' => route('cashouts.update', $cashout),
            'backUrl' => route('player-games.show', $participant),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Request $request,
        Cashout $cashout,
        CorrectCashout $action,
    ): RedirectResponse {
        $action->correct(
            $cashout,
            $request->user(),
            $request->only([
                'ending_chips',
                'cash_paid',
                'reason',
                'revision',
                'submission_id',
            ]),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cashout corrected. Review the correction history below.',
        ]);

        return to_route('cashouts.corrections', $cashout);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cashout $cashout): void
    {
        //
    }
}
