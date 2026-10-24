<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
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
        $players = Player::query()
            ->where('balance', '!=', 0)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'balance',
            ]);

        return Inertia::render('payments/Create', [
            'players' => $players,
            'defaultPaidAt' => today()->toDateString(),
            'submissionId' => (string) Str::uuid(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StorePaymentRequest $request,
        RecordPayment $action,
    ): RedirectResponse {
        $player = Player::query()
            ->findOrFail($request->integer('player_id'));

        $action->record(
            $player,
            $request->user(),
            (int) $request->validated('amount'),
            $request->validated('direction'),
            $request->validated('paid_at'),
            $request->validated('submission_id'),
            $request->validated('note'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Payment recorded successfully.',
        ]);

        return to_route('payments.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment): void
    {
        //
    }
}
