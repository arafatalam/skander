<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSettings\UpdateGameSettingRequest;
use App\Models\GameSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GameSettingController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(): Response
    {
        $settings = GameSetting::query()->findOrFail(1);

        return Inertia::render('settings/GameSettings', [
            'settings' => [
                'default_min_buy_in' => $settings->default_min_buy_in,
                'default_max_buy_in' => $settings->default_max_buy_in,
            ],
        ]);
    }

    public function editStandalone(): Response
    {
        $settings = GameSetting::query()->findOrFail(1);

        return Inertia::render('game-settings/Edit', [
            'settings' => [
                'default_min_buy_in' => $settings->default_min_buy_in,
                'default_max_buy_in' => $settings->default_max_buy_in,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGameSettingRequest $request): RedirectResponse
    {

        $settings = GameSetting::query()->findOrFail(1);

        $settings->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Default buy-in limits updated successfully.',
        ]);

        return back();
    }
}
