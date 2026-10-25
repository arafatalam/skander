<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import BuyInController from '@/actions/App/Http/Controllers/BuyInController';
import CashoutController from '@/actions/App/Http/Controllers/CashoutController';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});
type Direction = 'collect' | 'pay';
type Balances = {
    total: number;
    count: number;
    url: string;
    players: { id: number; name: string; amount: number; url: string }[];
};
type Game = {
    id: number;
    name: string;
    url: string;
    still_playing?: number;
    totals: { buy_ins: number; ending_chips: number; income: number | null };
};
const props = defineProps<{
    overview: {
        date: string;
        month: string;
        outstanding: Record<Direction, Balances>;
        income: { total: number; count: number; url: string };
        open_games: Game[];
        recent_games: Game[];
    };
}>();
const direction = ref<Direction>('collect');
const balances = computed(() => props.overview.outstanding[direction.value]);
const money = (value: number | null) =>
    value === null ? '—' : `$${value.toLocaleString('en-US')}`;
let interval: ReturnType<typeof setInterval> | undefined;
let refreshing = false;
let cancelRefresh: (() => void) | undefined;
function refresh() {
    if (document.hidden || refreshing) return;
    refreshing = true;
    router.reload({
        only: ['overview'],
        showProgress: false,
        onCancelToken: (token) => {
            cancelRefresh = () => token.cancel();
        },
        onFinish: () => {
            refreshing = false;
            cancelRefresh = undefined;
        },
        onNetworkError: () => false,
        onHttpException: () => false,
    });
}
onMounted(() => {
    refresh();
    interval = setInterval(refresh, 30_000);
    document.addEventListener('visibilitychange', refresh);
});
onUnmounted(() => {
    clearInterval(interval);
    cancelRefresh?.();
    document.removeEventListener('visibilitychange', refresh);
});
</script>

<template>
    <Head title="Dashboard" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading title="Dashboard" :description="overview.date" />
        <div class="flex flex-wrap gap-2">
            <Button as-child
                ><Link :href="GameSessionController.create()"
                    >+ Create game</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link :href="BuyInController.create()"
                    >Record buy-in</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link :href="CashoutController.create()"
                    >Record cashout</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link :href="PaymentController.create()"
                    >Record payment</Link
                ></Button
            >
        </div>
        <section aria-label="House summary" class="grid gap-4 md:grid-cols-3">
            <Link
                v-for="side in ['collect', 'pay'] as const"
                :key="side"
                :href="overview.outstanding[side].url"
                class="min-w-0 space-y-3 rounded-xl border border-sidebar-border/70 bg-card p-5 hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
            >
                <h2 class="font-medium">
                    {{
                        side === 'collect'
                            ? 'Players owe the house'
                            : 'House owes players'
                    }}
                </h2>
                <p class="text-3xl font-semibold break-words tabular-nums">
                    {{ money(overview.outstanding[side].total) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ overview.outstanding[side].count }} players · current
                    balances
                </p>
            </Link>
            <Link
                :href="overview.income.url"
                class="min-w-0 space-y-3 rounded-xl border border-sidebar-border/70 bg-card p-5 hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
            >
                <h2 class="font-medium">Income this month</h2>
                <p class="text-3xl font-semibold break-words tabular-nums">
                    {{ money(overview.income.total) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ overview.income.count }} closed games · game dates in
                    {{ overview.month }}
                </p>
            </Link>
        </section>
        <section
            aria-labelledby="open-heading"
            class="rounded-xl border border-sidebar-border/70 bg-card"
        >
            <div
                class="flex items-center justify-between gap-3 border-b p-4 sm:p-5"
            >
                <h2 id="open-heading" class="font-semibold">Open games</h2>
                <span class="text-sm text-muted-foreground"
                    >{{ overview.open_games.length }} games</span
                >
            </div>
            <div
                v-if="!overview.open_games.length"
                class="flex flex-wrap items-center justify-between gap-3 p-5"
            >
                <p class="text-muted-foreground">No open games.</p>
                <Button variant="outline" as-child
                    ><Link :href="GameSessionController.create()"
                        >Create game</Link
                    ></Button
                >
            </div>
            <article
                v-for="game in overview.open_games"
                :key="game.id"
                class="flex flex-wrap items-center justify-between gap-4 border-b p-4 last:border-0 sm:p-5"
            >
                <div class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold break-all">{{ game.name }}</h3>
                        <Badge variant="secondary">Open</Badge>
                    </div>
                    <dl class="flex flex-wrap gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Still playing</dt>
                            <dd class="font-medium">
                                {{ game.still_playing }} players
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Buy-ins</dt>
                            <dd class="font-medium tabular-nums">
                                {{ money(game.totals.buy_ins) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Cashout</dt>
                            <dd class="font-medium tabular-nums">
                                {{ money(game.totals.ending_chips) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Income</dt>
                            <dd>—</dd>
                        </div>
                    </dl>
                </div>
                <Button variant="outline" as-child
                    ><Link
                        :href="game.url"
                        :aria-label="`View game ${game.name}`"
                        >View game →</Link
                    ></Button
                >
            </article>
        </section>
        <div class="grid min-w-0 gap-6 lg:grid-cols-2">
            <section
                aria-labelledby="balances-heading"
                class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b p-4 sm:p-5"
                >
                    <h2 id="balances-heading" class="font-semibold">
                        Outstanding balances
                    </h2>
                    <div class="flex gap-2" aria-label="Owing direction">
                        <Button
                            v-for="side in ['collect', 'pay'] as const"
                            :key="side"
                            :variant="
                                direction === side ? 'default' : 'outline'
                            "
                            :aria-pressed="direction === side"
                            size="sm"
                            @click="direction = side"
                            >{{
                                side === 'collect' ? 'To collect' : 'To pay'
                            }}</Button
                        >
                    </div>
                </div>
                <table class="w-full table-auto text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Player
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{
                                    direction === 'collect'
                                        ? 'Player owes'
                                        : 'House owes'
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="player in balances.players"
                            :key="player.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 break-words">
                                <Link
                                    :href="player.url"
                                    class="underline-offset-4 hover:underline"
                                    >{{ player.name }}</Link
                                >
                            </td>
                            <td
                                class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ money(player.amount) }}
                            </td>
                        </tr>
                        <tr v-if="!balances.players.length">
                            <td
                                colspan="2"
                                class="p-5 text-center text-muted-foreground"
                            >
                                {{
                                    direction === 'collect'
                                        ? 'No players owe the house.'
                                        : 'The house owes no players.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t p-4"
                >
                    <span class="text-sm text-muted-foreground"
                        >Largest {{ balances.players.length }} of
                        {{ balances.count }} outstanding balances</span
                    ><Button variant="outline" size="sm" as-child
                        ><Link :href="balances.url">View all →</Link></Button
                    >
                </div>
            </section>
            <section
                aria-labelledby="recent-heading"
                class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b p-4 sm:p-5"
                >
                    <h2 id="recent-heading" class="font-semibold">
                        Recent closed games
                    </h2>
                    <Button variant="outline" size="sm" as-child
                        ><Link :href="GameSessionController.index()"
                            >All games →</Link
                        ></Button
                    >
                </div>
                <table class="w-full table-auto text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Game
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Income
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="game in overview.recent_games"
                            :key="game.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 break-all">
                                <Link
                                    :href="game.url"
                                    class="underline-offset-4 hover:underline"
                                    >{{ game.name }}</Link
                                >
                            </td>
                            <td
                                class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ money(game.totals.income) }}
                            </td>
                        </tr>
                        <tr v-if="!overview.recent_games.length">
                            <td
                                colspan="2"
                                class="p-5 text-center text-muted-foreground"
                            >
                                No closed games yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="border-t p-4 text-sm text-muted-foreground">
                    Most recent first · closed games only
                </p>
            </section>
        </div>
    </main>
</template>
