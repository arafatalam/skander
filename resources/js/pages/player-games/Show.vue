<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    playerGame: {
        id: number;
        player_name: string;
        game_name: string;
        opening_balance: number;
        opening_balance_label: string;
    };
}>();
</script>

<template>
    <Head title="Player game record" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            :title="playerGame.player_name"
            :description="playerGame.game_name"
        />

        <section
            class="max-w-xl rounded-xl border border-sidebar-border/70 bg-card p-6"
        >
            <h2 class="text-sm font-medium text-muted-foreground">
                Opening balance
            </h2>

            <p class="mt-2 text-xl font-semibold">
                {{ playerGame.opening_balance_label }}
            </p>

            <p class="mt-2 text-sm text-muted-foreground">
                Balance carried into this game from earlier activity.
            </p>
        </section>

        <div>
            <Button variant="outline" as-child>
                <Link :href="GameSessionController.index()">
                    Back to games
                </Link>
            </Button>
        </div>
    </main>
</template>

<style scoped></style>
