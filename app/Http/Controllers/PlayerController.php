<?php

namespace App\Http\Controllers;

use App\Actions\Players\GetPlayerHistory;
use App\Http\Requests\Players\StorePlayerRequest;
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
    public function index(Request $request): Response
    {
        $search = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ])['search'] ?? '';

        // TODO Revisit this following block of code
        $players = Player::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'is_active']);

        return Inertia::render('players/Index', [
            'players' => $players,
            'search' => $search,
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
        Player $player,
        GetPlayerHistory $getPlayerHistory,
    ): Response {
        return Inertia::render(
            'players/Show',
            $getPlayerHistory->get($player),
        );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return void
     */
    public function edit(Player $player)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return void
     */
    public function update(Request $request, Player $player)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return void
     */
    public function destroy(Player $player)
    {
        //
    }
}
