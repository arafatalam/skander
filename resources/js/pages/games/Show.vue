<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CorrectionHistoryController from '@/actions/App/Http/Controllers/CorrectionHistoryController';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const confirmationOpen = ref(false);

type Visit = {
    id: number;
    visit_number: number;
    buy_ins: { id: number; type: 'cash' | 'credit'; amount: number }[];
    cashout: {
        ending_chips: number;
        cash_paid: number;
        creation_method: 'manual' | 'automatic';
        edit_url: string;
    } | null;
};

type Player = {
    id: number;
    name: string;
    daily_balance: number;
    visits: Visit[];
};

const money = (amount: number): string => `$${amount.toLocaleString('en-US')}`;

function balanceLabel(balance: number): string {
    if (balance === 0) return 'Nothing owed';

    return balance > 0
        ? `House owes player ${money(balance)}`
        : `Player owes house ${money(Math.abs(balance))}`;
}

defineProps<{
    backUrl: string;
    returnFilters: Record<string, string | number | null>;
    game: {
        id: number;
        game_name: string;
        status: 'open' | 'closed';
        total_tips: number | null;
        total_cost: number | null;
        totals: {
            buy_ins: number;
            ending_chips: number;
            cash_paid: number;
            income: number | null;
        };
        players: Player[];
    };
}>();
</script>

<template>
    <Head :title="game.game_name" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Link :href="backUrl" class="text-sm underline">Back to games</Link>
        <Heading
            :title="game.game_name"
            :description="game.status === 'open' ? 'Open game' : 'Closed game'"
        />

        <div v-if="game.status === 'open'">
            <Dialog v-model:open="confirmationOpen">
                <DialogTrigger as-child>
                    <Button variant="destructive">Close game</Button>
                </DialogTrigger>

                <DialogContent class="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Close {{ game.game_name }}?</DialogTitle>
                        <DialogDescription>
                            Each player still playing will receive an automatic
                            cashout of $0 and $0 cash paid for their open visit.
                            Existing cashouts and player balances will stay
                            unchanged. Closing stops new buy-ins and ordinary
                            cashouts in this game.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-bind="
                            GameSessionController.close.form(game.id, {
                                query: returnFilters,
                            })
                        "
                        v-slot="{ errors, processing }"
                        @success="confirmationOpen = false"
                        class="space-y-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Record any actual cashout amount and cash paid
                            before closing.
                        </p>
                        <ul
                            v-if="Object.keys(errors).length"
                            role="alert"
                            class="space-y-1 text-sm text-destructive"
                        >
                            <li v-for="(message, field) in errors" :key="field">
                                {{ message }}
                            </li>
                        </ul>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="processing"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                            >
                                {{
                                    processing
                                        ? 'Closing...'
                                        : 'Confirm close game'
                                }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            <Link
                v-if="game.total_tips === null || game.total_cost === null"
                :href="GameSessionController.editTotals(game.id)"
                class="underline"
                >Record totals</Link
            >
            <Link
                :href="
                    CorrectionHistoryController.gameTotal({
                        gameSession: game.id,
                        field: 'total_tips',
                    })
                "
                class="underline"
                >Tip correction history</Link
            >
            <Link
                :href="
                    CorrectionHistoryController.gameTotal({
                        gameSession: game.id,
                        field: 'total_cost',
                    })
                "
                class="underline"
                >Cost correction history</Link
            >
        </div>
        <section aria-labelledby="game-totals-heading" class="space-y-4">
            <h2 id="game-totals-heading" class="text-lg font-semibold">
                Game totals
            </h2>
            <p class="text-sm text-muted-foreground">
                Recorded activity across all visits. Cashout and cash paid
                include completed cashouts only.
            </p>
            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="total in [
                        { label: 'Total buy-ins', amount: game.totals.buy_ins },
                        {
                            label: 'Cashout',
                            amount: game.totals.ending_chips,
                        },
                        {
                            label: 'Cash paid at cashout',
                            amount: game.totals.cash_paid,
                        },
                        { label: 'Total tips', amount: game.total_tips },
                        { label: 'Total game cost', amount: game.total_cost },
                        { label: 'Income', amount: game.totals.income },
                    ]"
                    :key="total.label"
                    class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ total.label }}
                    </dt>
                    <dd
                        class="mt-2 text-xl font-semibold break-words tabular-nums"
                    >
                        {{
                            total.amount === null
                                ? total.label === 'Income'
                                    ? '—'
                                    : 'Not recorded'
                                : money(total.amount)
                        }}
                    </dd>
                </div>
            </dl>
            <p class="text-sm text-muted-foreground">
                Tips and game costs do not change player balances.
            </p>
        </section>

        <h2 class="text-lg font-semibold">Players and visits</h2>

        <p
            v-if="game.players.length === 0"
            class="text-sm text-muted-foreground"
        >
            No players have joined this game yet.
        </p>

        <section
            v-for="player in game.players"
            :key="player.id"
            class="min-w-0 space-y-5 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6"
        >
            <h3 class="text-lg font-semibold break-words">{{ player.name }}</h3>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-muted-foreground">
                        Balance at end of day
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ balanceLabel(player.daily_balance) }}
                    </dd>
                </div>
            </dl>

            <p
                v-if="player.visits.length === 0"
                class="text-sm text-muted-foreground"
            >
                No visits recorded for this player.
            </p>

            <section
                v-for="visit in player.visits"
                :key="visit.id"
                class="space-y-4 rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="font-medium">Visit {{ visit.visit_number }}</h4>
                    <Badge
                        :variant="
                            visit.cashout === null ? 'default' : 'secondary'
                        "
                    >
                        {{
                            visit.cashout === null
                                ? 'Still playing'
                                : 'Cashed out'
                        }}
                    </Badge>
                </div>

                <div>
                    <h5 class="text-sm font-medium">Buy-ins</h5>
                    <p
                        v-if="visit.buy_ins.length === 0"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        No buy-ins recorded for this visit.
                    </p>
                    <ul v-else class="mt-2 divide-y text-sm">
                        <li
                            v-for="buyIn in visit.buy_ins"
                            :key="buyIn.id"
                            class="flex flex-wrap justify-between gap-2 py-2"
                        >
                            <span
                                >{{
                                    buyIn.type === 'cash' ? 'Cash' : 'Credit'
                                }}
                                buy-in</span
                            >
                            <span class="font-medium tabular-nums">{{
                                money(buyIn.amount)
                            }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="visit.cashout" class="space-y-3 border-t pt-4">
                    <h5 class="text-sm font-medium">Cashout</h5>
                    <Link
                        :href="visit.cashout.edit_url"
                        class="text-sm underline"
                        >Correct cashout</Link
                    >
                    <p
                        v-if="visit.cashout.creation_method === 'automatic'"
                        class="text-sm text-muted-foreground"
                    >
                        Automatically recorded when the game was closed.
                    </p>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">Cashout</dt>
                            <dd class="mt-1 font-medium tabular-nums">
                                {{ money(visit.cashout.ending_chips) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Cash paid</dt>
                            <dd class="mt-1 font-medium tabular-nums">
                                {{ money(visit.cashout.cash_paid) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
        </section>
    </main>
</template>
