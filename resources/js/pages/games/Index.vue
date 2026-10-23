<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineProps<{
    games: {
        id: number;
        game_date: string;
        game_number: number;
        game_name: string;
        status: 'open' | 'closed';
        min_buy_in: number;
        max_buy_in: number;
    }[];
}>();
</script>

<template>
    <Head title="Games" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Games" description="View your recorded games." />

            <Button as-child>
                <Link :href="GameSessionController.create()">
                    Create game
                </Link>
            </Button>
        </div>

        <section
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Game</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Minimum buy-in
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Maximum buy-in
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="game in games"
                        :key="game.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ game.game_name }}
                        </td>

                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    game.status === 'open'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ game.status === 'open' ? 'Open' : 'Closed' }}
                            </Badge>
                        </td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                        >
                            ${{ game.min_buy_in.toLocaleString('en-US') }}
                        </td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                        >
                            ${{ game.max_buy_in.toLocaleString('en-US') }}
                        </td>
                    </tr>

                    <tr v-if="games.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No games have been created yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</template>
