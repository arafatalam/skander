<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import CorrectionHistoryController from '@/actions/App/Http/Controllers/CorrectionHistoryController';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
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

type Purchase = {
    id: number;
    label: string;
    visit_number: number;
    cash: number;
    credit: number;
    total: number;
};

type Player = {
    id: number;
    name: string;
    participation_status: string;
    total_cash_buy_in: number;
    total_credit_buy_in: number;
    total_buy_in: number;
    total_cashout: number | null;
    total_cash_paid: number | null;
    book_balance_change: number | null;
    book_balance_change_label: string;
    purchases: Purchase[];
};

const money = (amount: number): string => `$${amount.toLocaleString('en-US')}`;

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

        <section aria-labelledby="players-heading" class="space-y-4">
            <h2 id="players-heading" class="text-lg font-semibold">Players</h2>
            <p class="text-sm text-muted-foreground">
                Expand a player to see their buy-ins. Game balance change
                includes this game's credit buy-ins, ending chips and cash paid
                across all visits; later payments are excluded.
            </p>
            <p
                v-if="game.players.length === 0"
                class="rounded-xl border p-4 text-sm text-muted-foreground"
            >
                No players have joined this game yet.
            </p>
            <div
                v-else
                class="min-w-0 divide-y rounded-xl border border-sidebar-border/70 bg-card"
            >
                <details
                    v-for="player in game.players"
                    :key="player.id"
                    class="group min-w-0 p-3 sm:p-5"
                >
                    <summary
                        class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-4 rounded-md focus-visible:outline-2 focus-visible:outline-ring xl:flex-nowrap [&::-webkit-details-marker]:hidden"
                    >
                        <span
                            class="flex min-w-0 items-center gap-2 xl:w-64 xl:shrink-0"
                        >
                            <ChevronRight
                                class="size-4 shrink-0 group-open:rotate-90"
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold break-words">{{
                                    player.name
                                }}</span>
                                <span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{ player.participation_status }}</span
                                >
                            </span>
                        </span>
                        <span
                            class="grid w-full min-w-0 grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-[minmax(max-content,1.2fr)_1fr_1fr_1.3fr] xl:ml-auto xl:w-auto xl:flex-1 xl:pl-4"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Total buy-in</span
                                >
                                <span
                                    class="mt-1 block font-medium tabular-nums"
                                    >{{ money(player.total_buy_in) }}</span
                                >
                                <span
                                    class="mt-1 block text-xs whitespace-nowrap text-muted-foreground"
                                    >{{ money(player.total_cash_buy_in) }}
                                    cash ·
                                    {{ money(player.total_credit_buy_in) }}
                                    cr</span
                                >
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Total cashout</span
                                >
                                <span
                                    class="mt-1 block font-medium tabular-nums"
                                    >{{
                                        player.total_cashout === null
                                            ? '—'
                                            : money(player.total_cashout)
                                    }}</span
                                >
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Cash paid</span
                                >
                                <span
                                    class="mt-1 block font-medium tabular-nums"
                                    >{{
                                        player.total_cash_paid === null
                                            ? '—'
                                            : money(player.total_cash_paid)
                                    }}</span
                                >
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Game balance change</span
                                >
                                <span
                                    class="mt-1 block font-medium tabular-nums"
                                    >{{
                                        player.book_balance_change_label
                                    }}</span
                                >
                                <span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{
                                        player.book_balance_change === null
                                            ? 'Still playing'
                                            : 'This game only'
                                    }}</span
                                >
                            </span>
                        </span>
                    </summary>
                    <div class="mt-5 min-w-0 border-t pt-4">
                        <h3 class="mb-3 text-sm font-medium">Buy-in history</h3>
                        <p
                            v-if="player.purchases.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No buy-ins recorded for this player.
                        </p>
                        <div v-else class="overflow-x-auto">
                            <table
                                class="w-full table-fixed text-center text-sm"
                            >
                                <caption class="sr-only">
                                    Buy-in history for
                                    {{
                                        player.name
                                    }}
                                </caption>
                                <thead
                                    class="bg-muted/50 text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            scope="col"
                                            class="px-1 py-3 text-center font-medium sm:px-3"
                                        >
                                            Purchase
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-1 py-3 text-center font-medium sm:px-3"
                                        >
                                            Cash
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-1 py-3 text-center font-medium sm:px-3"
                                        >
                                            Credit
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-1 py-3 text-center font-medium sm:px-3"
                                        >
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="purchase in player.purchases"
                                        :key="purchase.id"
                                    >
                                        <td class="px-1 py-3 sm:px-3">
                                            {{ purchase.label }}
                                            <span
                                                class="block text-xs text-muted-foreground"
                                                >Visit
                                                {{
                                                    purchase.visit_number
                                                }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-1 py-3 text-center tabular-nums sm:px-3"
                                        >
                                            {{ money(purchase.cash) }}
                                        </td>
                                        <td
                                            class="px-1 py-3 text-center tabular-nums sm:px-3"
                                        >
                                            {{ money(purchase.credit) }}
                                        </td>
                                        <td
                                            class="px-1 py-3 text-center font-medium tabular-nums sm:px-3"
                                        >
                                            {{ money(purchase.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </div>
        </section>
    </main>
</template>
