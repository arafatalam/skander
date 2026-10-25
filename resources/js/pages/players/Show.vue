<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import CorrectionHistoryController from '@/actions/App/Http/Controllers/CorrectionHistoryController';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
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

type Purchase = {
    id: number;
    label: string;
    visit_number: number;
    cash: number;
    credit: number;
    total: number;
};

type Game = {
    id: number;
    game_name: string;
    status: 'open' | 'closed';
    total_cash_buy_in: number;
    total_credit_buy_in: number;
    total_buy_in: number;
    total_cashout: number | null;
    total_cash_paid: number | null;
    book_balance_change: number | null;
    book_balance_change_label: string;
    purchases: Purchase[];
};

type Payment = {
    id: number;
    amount: number;
    direction: 'house_pays_player' | 'house_collects_from_player';
    direction_label: string;
    paid_at: string;
    recorded_at: string;
    note: string | null;
    edit_url: string;
};

defineProps<{
    player: {
        id: number;
        name: string;
        balance_label: string;
        balance_amount: number;
        balance_direction: string;
        is_active: boolean;
    };
    backUrl: string;
    backLabel: string;
    games: Game[];
    payments: Payment[];
}>();

function formatMoney(amount: number): string {
    return `$${amount.toLocaleString('en-US')}`;
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Moncton',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatPaymentDate(value: string): string {
    // A payment date has no time or timezone. Preserve its calendar day.
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'UTC',
        dateStyle: 'medium',
    }).format(new Date(`${value}T00:00:00Z`));
}
</script>

<template>
    <Head :title="`${player.name} — History`" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="player.name"
                description="Game activity and later payments."
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="backUrl">{{ backLabel }}</Link>
                </Button>
                <Button as-child>
                    <Link :href="PlayerController.edit(player.id)"
                        >Edit player profile</Link
                    >
                </Button>
                <Dialog v-if="player.is_active" v-model:open="confirmationOpen">
                    <DialogTrigger as-child>
                        <Button variant="destructive">Deactivate player</Button>
                    </DialogTrigger>
                    <DialogContent class="max-h-[90dvh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle class="break-words"
                                >Deactivate {{ player.name }}?</DialogTitle
                            >
                            <DialogDescription>
                                This player will no longer be available for
                                buy-ins, including top-ups and returns. Their
                                game history and balance will remain visible.
                                You can still record cashouts and payments.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            v-bind="PlayerController.deactivate.form(player.id)"
                            v-slot="{ errors, processing }"
                            @success="confirmationOpen = false"
                            class="space-y-4"
                        >
                            <ul
                                v-if="Object.keys(errors).length"
                                role="alert"
                                class="text-sm text-destructive"
                            >
                                <li
                                    v-for="(message, field) in errors"
                                    :key="field"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="processing"
                                        >Cancel</Button
                                    >
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                    >{{
                                        processing
                                            ? 'Deactivating...'
                                            : 'Confirm deactivation'
                                    }}</Button
                                >
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <p v-if="!player.is_active" class="text-sm text-muted-foreground">
            <Badge variant="secondary" class="mr-2">Inactive</Badge>
            New buy-ins are blocked. History, cashouts, and payments remain
            available.
        </p>

        <section
            aria-labelledby="current-balance-heading"
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6"
        >
            <h2
                id="current-balance-heading"
                class="text-sm font-medium text-muted-foreground"
            >
                Current book balance
            </h2>
            <p class="mt-2 text-xl font-semibold break-words">
                {{ player.balance_label }}
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                Includes recorded game activity and later payments.
            </p>
        </section>

        <section aria-labelledby="games-heading" class="space-y-4">
            <h2 id="games-heading" class="text-lg font-semibold">
                Game history
            </h2>
            <p class="text-sm text-muted-foreground">
                Oldest participation first. Expand a game to see its buy-ins.
                Current book balance includes all games and later payments. Game
                balance change includes only that game's credit buy-ins, ending
                chips and cash paid, across all visits.
            </p>
            <p
                v-if="games.length === 0"
                class="rounded-xl border p-4 text-sm text-muted-foreground"
            >
                No games recorded for this player.
            </p>

            <div
                v-else
                class="min-w-0 divide-y rounded-xl border border-sidebar-border/70 bg-card"
            >
                <details
                    v-for="game in games"
                    :key="game.id"
                    class="group min-w-0 p-3 sm:p-5"
                >
                    <summary
                        class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-4 rounded-md focus-visible:outline-2 focus-visible:outline-ring xl:flex-nowrap [&::-webkit-details-marker]:hidden"
                    >
                        <span
                            class="flex min-w-0 items-center gap-2 xl:shrink-0"
                        >
                            <ChevronRight
                                class="size-4 shrink-0 group-open:rotate-90"
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold break-words">{{
                                    game.game_name
                                }}</span>
                                <span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{
                                        game.status === 'open'
                                            ? 'Open'
                                            : 'Closed'
                                    }}</span
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
                                    >{{ formatMoney(game.total_buy_in) }}</span
                                >
                                <span
                                    class="mt-1 block text-xs whitespace-nowrap text-muted-foreground"
                                    >{{ formatMoney(game.total_cash_buy_in) }}
                                    cash ·
                                    {{ formatMoney(game.total_credit_buy_in) }}
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
                                        game.total_cashout === null
                                            ? '—'
                                            : formatMoney(game.total_cashout)
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
                                        game.total_cash_paid === null
                                            ? '—'
                                            : formatMoney(game.total_cash_paid)
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
                                    >{{ game.book_balance_change_label }}</span
                                >
                                <span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{
                                        game.book_balance_change === null
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
                            v-if="game.purchases.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No buy-ins recorded for this game.
                        </p>
                        <div v-else class="overflow-x-auto">
                            <table
                                class="w-full table-fixed text-center text-sm"
                            >
                                <caption class="sr-only">
                                    Buy-in history for
                                    {{
                                        game.game_name
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
                                        v-for="purchase in game.purchases"
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
                                            {{ formatMoney(purchase.cash) }}
                                        </td>
                                        <td
                                            class="px-1 py-3 text-center tabular-nums sm:px-3"
                                        >
                                            {{ formatMoney(purchase.credit) }}
                                        </td>
                                        <td
                                            class="px-1 py-3 text-center font-medium tabular-nums sm:px-3"
                                        >
                                            {{ formatMoney(purchase.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </div>
        </section>

        <section aria-labelledby="payments-heading" class="space-y-4">
            <h2 id="payments-heading" class="text-lg font-semibold">
                Later payments
            </h2>
            <p class="text-sm text-muted-foreground">
                Oldest payment date first. Recorded times are shown in Moncton
                time.
            </p>
            <p
                v-if="payments.length === 0"
                class="rounded-xl border p-4 text-sm text-muted-foreground"
            >
                No later payments recorded for this player.
            </p>
            <div
                v-else
                class="min-w-0 overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card"
            >
                <table
                    class="w-full min-w-[1000px] table-fixed text-center text-sm"
                >
                    <caption class="sr-only">
                        Later payments for
                        {{
                            player.name
                        }}
                    </caption>
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th
                                scope="col"
                                class="px-2 py-3 font-medium sm:px-4"
                            >
                                Payment date
                            </th>
                            <th
                                scope="col"
                                class="px-2 py-3 font-medium sm:px-4"
                            >
                                Direction
                            </th>
                            <th
                                scope="col"
                                class="px-2 py-3 font-medium sm:px-4"
                            >
                                Amount
                            </th>
                            <th
                                scope="col"
                                class="px-2 py-3 font-medium sm:px-4"
                            >
                                Recorded at
                            </th>
                            <th
                                scope="col"
                                class="w-[24%] px-2 py-3 font-medium sm:px-4"
                            >
                                Note
                            </th>
                            <th
                                scope="col"
                                class="w-[20%] px-2 py-3 font-medium sm:px-4"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        v-for="payment in payments"
                        :key="payment.id"
                        class="border-t"
                    >
                        <tr>
                            <td class="px-2 py-3 align-middle sm:px-4">
                                <time :datetime="payment.paid_at">{{
                                    formatPaymentDate(payment.paid_at)
                                }}</time>
                            </td>
                            <td class="px-2 py-3 align-middle sm:px-4">
                                {{ payment.direction_label }}
                            </td>
                            <td
                                class="px-2 py-3 align-middle font-medium break-words tabular-nums sm:px-4"
                            >
                                {{ formatMoney(payment.amount) }}
                            </td>
                            <td class="px-2 py-3 align-middle sm:px-4">
                                <time :datetime="payment.recorded_at">{{
                                    formatDateTime(payment.recorded_at)
                                }}</time>
                            </td>
                            <td
                                class="px-2 py-3 align-middle break-words whitespace-pre-wrap sm:px-4"
                            >
                                {{ payment.note || '—' }}
                            </td>
                            <td class="px-2 py-3 align-middle sm:px-4">
                                <div
                                    class="flex flex-wrap justify-center gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                CorrectionHistoryController.payment(
                                                    payment.id,
                                                )
                                            "
                                            >Correction history</Link
                                        >
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="payment.edit_url"
                                            >Correct payment</Link
                                        >
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
