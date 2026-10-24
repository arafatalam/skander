<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    game: {
        id: number;
        game_name: string;
        total_tips: number | null;
        total_cost: number | null;
    };
}>();
</script>

<template>
    <Head title="Record game totals" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading title="Record game totals" :description="game.game_name" />

        <Form
            v-bind="GameSessionController.recordTotals.form(game.id)"
            v-slot="{ errors, processing }"
            novalidate
            class="max-w-xl space-y-6"
        >
            <p class="text-sm text-muted-foreground">
                Enter the dealer-reported tip total and the total game cost. You
                can record either amount separately or both together. Leave an
                unknown amount blank; enter 0 to record zero.
            </p>

            <div class="grid gap-2">
                <template v-if="game.total_tips === null">
                    <Label for="total_tips">Total tips ($)</Label>
                    <Input
                        id="total_tips"
                        name="total_tips"
                        type="number"
                        min="0"
                        max="2147483647"
                        step="1"
                        :readonly="processing"
                        :aria-invalid="Boolean(errors.total_tips)"
                        aria-describedby="total_tips_error"
                    />
                </template>
                <template v-else>
                    <h2 class="text-sm font-medium">Total tips ($)</h2>
                    <p class="font-medium">
                        Recorded: ${{ game.total_tips.toLocaleString('en-US') }}
                    </p>
                </template>
                <InputError
                    id="total_tips_error"
                    :message="errors.total_tips"
                />
            </div>

            <div class="grid gap-2">
                <template v-if="game.total_cost === null">
                    <Label for="total_cost">Total game cost ($)</Label>
                    <Input
                        id="total_cost"
                        name="total_cost"
                        type="number"
                        min="0"
                        max="2147483647"
                        step="1"
                        :readonly="processing"
                        :aria-invalid="Boolean(errors.total_cost)"
                        aria-describedby="total_cost_error"
                    />
                </template>
                <template v-else>
                    <h2 class="text-sm font-medium">Total game cost ($)</h2>
                    <p class="font-medium">
                        Recorded: ${{ game.total_cost.toLocaleString('en-US') }}
                    </p>
                </template>
                <InputError
                    id="total_cost_error"
                    :message="errors.total_cost"
                />
            </div>

            <p
                v-if="game.total_tips !== null && game.total_cost !== null"
                class="text-sm text-muted-foreground"
            >
                Both totals have been recorded.
            </p>

            <p class="text-sm text-muted-foreground">
                Tips and game costs do not change player balances. Recorded
                totals cannot be overwritten here.
            </p>

            <div class="flex flex-wrap gap-3">
                <Button
                    v-if="game.total_tips === null || game.total_cost === null"
                    type="submit"
                    :disabled="processing"
                >
                    {{ processing ? 'Saving...' : 'Record totals' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="GameSessionController.index()">
                        Back to games
                    </Link>
                </Button>
            </div>
        </Form>
    </main>
</template>
