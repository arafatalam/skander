<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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

type BuyIn = {
    id: number;
    amount: number;
    type: 'cash' | 'credit';
    recorded_at: string;
};

type Cashout = {
    id: number;
    ending_chips: number;
    cash_paid: number;
    creation_method: 'manual' | 'automatic';
    cashed_out_at: string;
    edit_url: string;
};

type Visit = {
    id: number;
    visit_number: number;
    opened_at: string;
    buy_ins: BuyIn[];
    cashout: Cashout | null;
};

type Game = {
    id: number;
    game_name: string;
    daily_balance_label: string;
    visits: Visit[];
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
        is_active: boolean;
    };
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
                    <Link :href="PlayerController.index()"
                        >Back to players</Link
                    >
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
                Oldest participation first. Activity times are shown in Moncton
                time.
            </p>
            <p
                v-if="games.length === 0"
                class="rounded-xl border p-4 text-sm text-muted-foreground"
            >
                No games recorded for this player.
            </p>

            <article
                v-for="game in games"
                :key="game.id"
                class="min-w-0 space-y-5 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6"
            >
                <h3 class="text-lg font-semibold break-words">
                    {{ game.game_name }}
                </h3>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Balance at end of day
                        </dt>
                        <dd class="mt-1 font-medium break-words">
                            {{ game.daily_balance_label }}
                        </dd>
                    </div>
                </dl>
                <p class="text-sm text-muted-foreground">
                    Includes all activity and payments dated on or before this
                    game’s date, across every game played that day.
                </p>
                <p
                    v-if="game.visits.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No visits recorded.
                </p>

                <section
                    v-for="visit in game.visits"
                    :key="visit.id"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h4 class="font-medium">
                            Visit {{ visit.visit_number }}
                        </h4>
                        <Badge variant="secondary">
                            {{
                                visit.cashout === null
                                    ? 'Still playing'
                                    : 'Cashed out'
                            }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Started
                        <time :datetime="visit.opened_at">{{
                            formatDateTime(visit.opened_at)
                        }}</time>
                    </p>
                    <div>
                        <h5 class="text-sm font-medium">Buy-ins</h5>
                        <p
                            v-if="visit.buy_ins.length === 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            No buy-ins recorded for this visit.
                        </p>
                        <ul v-else class="mt-2 divide-y">
                            <li
                                v-for="buyIn in visit.buy_ins"
                                :key="buyIn.id"
                                class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm"
                            >
                                <div>
                                    <p>
                                        {{
                                            buyIn.type === 'cash'
                                                ? 'Cash'
                                                : 'Credit'
                                        }}
                                        buy-in
                                    </p>
                                    <time
                                        :datetime="buyIn.recorded_at"
                                        class="text-muted-foreground"
                                    >
                                        {{ formatDateTime(buyIn.recorded_at) }}
                                    </time>
                                </div>
                                <Link
                                    :href="
                                        CorrectionHistoryController.buyIn(
                                            buyIn.id,
                                        )
                                    "
                                    class="text-sm underline"
                                    >Correction history</Link
                                >
                                <span class="font-medium tabular-nums">{{
                                    formatMoney(buyIn.amount)
                                }}</span>
                            </li>
                        </ul>
                    </div>
                    <div v-if="visit.cashout" class="space-y-3 border-t pt-4">
                        <Link
                            :href="visit.cashout.edit_url"
                            class="mr-4 text-sm underline"
                            >Correct cashout</Link
                        >
                        <Link
                            :href="
                                CorrectionHistoryController.cashout(
                                    visit.cashout.id,
                                )
                            "
                            class="text-sm underline"
                            >Correction history</Link
                        >

                        <h5 class="text-sm font-medium">
                            {{
                                visit.cashout.creation_method === 'automatic'
                                    ? 'Automatic cashout'
                                    : 'Manual cashout'
                            }}
                        </h5>
                        <time
                            :datetime="visit.cashout.cashed_out_at"
                            class="block text-sm text-muted-foreground"
                        >
                            {{ formatDateTime(visit.cashout.cashed_out_at) }}
                        </time>
                        <p
                            v-if="visit.cashout.creation_method === 'automatic'"
                            class="text-sm text-muted-foreground"
                        >
                            Recorded automatically when the game was closed.
                        </p>
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">
                                    Ending chips
                                </dt>
                                <dd class="mt-1 font-medium tabular-nums">
                                    {{
                                        formatMoney(visit.cashout.ending_chips)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Cash paid</dt>
                                <dd class="mt-1 font-medium tabular-nums">
                                    {{ formatMoney(visit.cashout.cash_paid) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </article>
        </section>

        <section aria-labelledby="payments-heading" class="space-y-4">
            <h2 id="payments-heading" class="text-lg font-semibold">
                Later payments
            </h2>
            <p class="text-sm text-muted-foreground">
                Oldest payment date first.
            </p>
            <p
                v-if="payments.length === 0"
                class="rounded-xl border p-4 text-sm text-muted-foreground"
            >
                No later payments recorded for this player.
            </p>
            <ul v-else class="space-y-4">
                <li
                    v-for="payment in payments"
                    :key="payment.id"
                    class="min-w-0 space-y-3 rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6"
                >
                    <Link
                        :href="CorrectionHistoryController.payment(payment.id)"
                        class="text-sm underline"
                        >Correction history</Link
                    >
                    <Link
                        :href="payment.edit_url"
                        class="ml-4 text-sm underline"
                        >Correct payment</Link
                    >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <h3 class="font-medium">
                            {{ payment.direction_label }}
                        </h3>
                        <span class="font-semibold tabular-nums">{{
                            formatMoney(payment.amount)
                        }}</span>
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">Payment date</dt>
                            <dd class="mt-1">
                                <time :datetime="payment.paid_at">{{
                                    formatPaymentDate(payment.paid_at)
                                }}</time>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Recorded at</dt>
                            <dd class="mt-1">
                                <time :datetime="payment.recorded_at">{{
                                    formatDateTime(payment.recorded_at)
                                }}</time>
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="payment.note"
                        class="text-sm break-words whitespace-pre-wrap"
                    >
                        {{ payment.note }}
                    </p>
                </li>
            </ul>
        </section>
    </main>
</template>
