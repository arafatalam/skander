<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Game = {
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
    show_url: string;
};
const props = defineProps<{
    games: {
        data: Game[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    filters: {
        from_date?: string | null;
        to_date?: string | null;
        status?: string | null;
    };
}>();
const form = useForm({
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    status: props.filters.status ?? 'all',
});
const money = (value: number | null): string =>
    value === null ? '—' : `$${value.toLocaleString('en-US')}`;
function applyFilters() {
    form.get(GameSessionController.index.url(), {
        preserveState: true,
        preserveScroll: true,
    });
}
function clearFilters() {
    form.from_date = '';
    form.to_date = '';
    form.status = 'all';
    form.clearErrors();
    applyFilters();
}
</script>

<template>
    <Head title="Games" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Games" description="View your recorded games." />
            <Button as-child
                ><Link :href="GameSessionController.create()"
                    >Create game</Link
                ></Button
            >
        </div>
        <form
            class="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-3"
            @submit.prevent="applyFilters"
        >
            <div class="space-y-2">
                <Label for="from-date">From date</Label>
                <Input id="from-date" v-model="form.from_date" type="date" />
                <InputError :message="form.errors.from_date" />
            </div>
            <div class="space-y-2">
                <Label for="to-date">To date</Label>
                <Input id="to-date" v-model="form.to_date" type="date" />
                <InputError :message="form.errors.to_date" />
            </div>
            <div class="space-y-2">
                <Label for="game-status">Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger id="game-status" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All games</SelectItem>
                        <SelectItem value="open">Open</SelectItem>
                        <SelectItem value="closed">Closed</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.status" />
            </div>
            <div class="flex flex-wrap gap-2 sm:col-span-3">
                <Button type="submit" :disabled="form.processing"
                    >Apply filters</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="clearFilters"
                    >Clear filters</Button
                >
            </div>
        </form>
        <section class="game-history min-w-0" aria-label="Game history">
            <div class="rounded-xl border border-sidebar-border/70 bg-card">
                <table class="game-table w-full text-center text-sm">
                    <thead class="border-b bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="game-cell">Game</th>
                            <th class="activity-cell">Buy-in / Cashout</th>
                            <th class="separate-activity text-center">
                                Buy-in
                            </th>
                            <th class="separate-activity text-center">
                                Cashout
                            </th>
                            <th class="cash-cell text-center">Cash paid</th>
                            <th class="totals-cell text-center">Tips</th>
                            <th class="totals-cell text-center">Game cost</th>
                            <th class="income-cell text-center">Income</th>
                            <th class="wide-only text-center">Status</th>
                            <th class="wide-only text-center">View game</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="game in games.data"
                            :key="game.id"
                            class="border-b last:border-0"
                        >
                            <td class="game-cell">
                                <Link
                                    :href="game.show_url"
                                    class="font-medium hover:underline"
                                    >{{ game.game_name }}</Link
                                >
                                <div
                                    class="compact-actions mt-2 flex flex-wrap items-center justify-center gap-1"
                                >
                                    <Badge
                                        :variant="
                                            game.status === 'open'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        class="h-6 px-2 text-[11px]"
                                        >{{
                                            game.status === 'open'
                                                ? 'Open'
                                                : 'Closed'
                                        }}</Badge
                                    >
                                    <Button
                                        variant="outline"
                                        as-child
                                        class="h-6 px-2 text-[11px]"
                                        ><Link
                                            :href="game.show_url"
                                            :aria-label="`View game ${game.game_name}`"
                                            >View game</Link
                                        ></Button
                                    >
                                </div>
                            </td>
                            <td class="activity-cell tabular-nums">
                                <div>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >Buy-in</span
                                    >{{ money(game.totals.buy_ins) }}
                                </div>
                                <div class="mt-2">
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >Cashout</span
                                    >{{ money(game.totals.ending_chips) }}
                                </div>
                            </td>
                            <td
                                class="separate-activity text-center tabular-nums"
                            >
                                {{ money(game.totals.buy_ins) }}
                            </td>
                            <td
                                class="separate-activity text-center tabular-nums"
                            >
                                {{ money(game.totals.ending_chips) }}
                            </td>
                            <td class="cash-cell text-center tabular-nums">
                                {{ money(game.totals.cash_paid) }}
                            </td>
                            <td class="totals-cell text-center tabular-nums">
                                {{ money(game.total_tips) }}
                            </td>
                            <td class="totals-cell text-center tabular-nums">
                                {{ money(game.total_cost) }}
                            </td>
                            <td
                                class="income-cell text-center font-medium tabular-nums"
                            >
                                {{ money(game.totals.income) }}
                            </td>
                            <td class="wide-only text-center">
                                <Badge
                                    :variant="
                                        game.status === 'open'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    >{{
                                        game.status === 'open'
                                            ? 'Open'
                                            : 'Closed'
                                    }}</Badge
                                >
                            </td>
                            <td class="wide-only text-center">
                                <Button variant="outline" size="sm" as-child
                                    ><Link :href="game.show_url"
                                        >View game</Link
                                    ></Button
                                >
                            </td>
                        </tr>
                        <tr v-if="games.data.length === 0">
                            <td
                                colspan="9"
                                class="py-8 text-center text-muted-foreground"
                            >
                                No games match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav
                class="mt-4 flex flex-wrap items-center justify-between gap-3"
                aria-label="Game pages"
            >
                <p class="text-sm text-muted-foreground">
                    Page {{ games.current_page }} of {{ games.last_page }} ·
                    {{ games.total }} games
                </p>
                <div class="flex gap-2">
                    <Button
                        v-if="games.prev_page_url"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link :href="games.prev_page_url"
                            >Previous</Link
                        ></Button
                    >
                    <Button v-else variant="outline" size="sm" disabled
                        >Previous</Button
                    >
                    <Button
                        v-if="games.next_page_url"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link :href="games.next_page_url">Next</Link></Button
                    >
                    <Button v-else variant="outline" size="sm" disabled
                        >Next</Button
                    >
                </div>
            </nav>
        </section>
    </main>
</template>

<style scoped>
.game-history {
    container-type: inline-size;
}
.game-table {
    table-layout: auto;
    font-size: 12px;
}
.game-table th,
.game-table td {
    padding: 12px 8px;
    vertical-align: top;
    overflow-wrap: anywhere;
}
.game-table th {
    font-weight: 500;
}
.income-cell {
    white-space: nowrap;
}
.wide-only,
.separate-activity,
.totals-cell,
.cash-cell {
    display: none;
}
@container (min-width: 600px) {
    .game-table {
        font-size: 14px;
    }
    .cash-cell {
        display: table-cell;
    }
    .game-table th,
    .game-table td {
        padding: 12px;
    }
}
@container (min-width: 800px) {
    .totals-cell {
        display: table-cell;
    }
}
@container (min-width: 1024px) {
    .wide-only {
        display: table-cell;
    }
    .compact-actions {
        display: none;
    }
}
@container (min-width: 1280px) {
    .activity-cell {
        display: none;
    }
    .separate-activity {
        display: table-cell;
    }
}
</style>
