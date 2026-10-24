<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import BuyInController from '@/actions/App/Http/Controllers/BuyInController';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    games: {
        id: number;
        game_name: string;
        min_buy_in: number;
        max_buy_in: number;
    }[];
    players: {
        id: number;
        name: string;
    }[];
    returnLimits: {
        game_session_id: number;
        player_id: number;
        minimum: number;
        maximum: number;
    }[];
}>();

const selectedGameId = ref('');
const selectedPlayerId = ref('');

const selectedReturnLimits = computed(() => {
    return props.returnLimits.find(
        (limits) =>
            String(limits.game_session_id) === selectedGameId.value &&
            String(limits.player_id) === selectedPlayerId.value,
    );
});

const selectedGame = computed(() => {
    for (const game of props.games) {
        const gameId = String(game.id);

        if (gameId === selectedGameId.value) {
            return game;
        }
    }
    return undefined;
});
</script>

<template>
    <Head title="Record buy-in" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Record buy-in"
            description="Choose a game and player, then enter the cash and credit amounts."
        />

        <Form
            v-bind="BuyInController.store.form()"
            novalidate
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="game_session_id">Game</Label>

                <Select v-model="selectedGameId" name="game_session_id">
                    <SelectTrigger id="game_session_id" class="w-full">
                        <SelectValue placeholder="Choose a game" />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem
                            v-for="game in games"
                            :key="game.id"
                            :value="String(game.id)"
                        >
                            {{ game.game_name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <p v-if="selectedGame" class="text-sm text-muted-foreground">
                    Normal buy-in limits: ${{
                        selectedGame.min_buy_in.toLocaleString('en-US')
                    }}
                    to ${{ selectedGame.max_buy_in.toLocaleString('en-US') }}.
                    Select a player to see their permitted combined amount.
                </p>

                <InputError :message="errors.game_session_id" />
            </div>

            <div class="grid gap-2">
                <Label for="player_id">Player</Label>

                <Select v-model="selectedPlayerId" name="player_id">
                    <SelectTrigger id="player_id" class="w-full">
                        <SelectValue placeholder="Choose a player" />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem
                            v-for="player in players"
                            :key="player.id"
                            :value="String(player.id)"
                        >
                            {{ player.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <InputError :message="errors.player_id" />
            </div>

            <div
                v-if="selectedGame && selectedPlayerId"
                class="space-y-1 text-sm text-muted-foreground"
                aria-live="polite"
            >
                <template v-if="selectedReturnLimits">
                    <p
                        v-if="
                            selectedReturnLimits.minimum ===
                            selectedReturnLimits.maximum
                        "
                    >
                        Returning buy-in: exactly ${{
                            selectedReturnLimits.minimum.toLocaleString('en-US')
                        }}
                        in cash and credit combined.
                    </p>
                    <p v-else>
                        Returning buy-in: ${{
                            selectedReturnLimits.minimum.toLocaleString('en-US')
                        }}
                        to ${{
                            selectedReturnLimits.maximum.toLocaleString('en-US')
                        }}
                        in cash and credit combined.
                    </p>
                    <p>
                        This amount is based on the latest cashout's ending
                        chips. Cash paid at cashout does not reduce it. Later
                        top-ups use the normal game limits.
                    </p>
                </template>
                <p v-else>
                    Permitted buy-in: ${{
                        selectedGame.min_buy_in.toLocaleString('en-US')
                    }}
                    to ${{ selectedGame.max_buy_in.toLocaleString('en-US') }}
                    in cash and credit combined.
                </p>
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="cash_amount">Cash amount ($)</Label>

                    <Input
                        id="cash_amount"
                        name="cash_amount"
                        type="number"
                        min="0"
                        step="1"
                        default-value="0"
                    />

                    <InputError :message="errors.cash_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="credit_amount">Credit amount ($)</Label>

                    <Input
                        id="credit_amount"
                        name="credit_amount"
                        type="number"
                        min="0"
                        step="1"
                        default-value="0"
                    />

                    <InputError :message="errors.credit_amount" />
                </div>

                <InputError
                    :message="errors.buy_in_total"
                    class="sm:col-span-2"
                />
            </div>

            <Button
                type="submit"
                :disabled="
                    processing || games.length === 0 || players.length === 0
                "
            >
                {{ processing ? 'Saving...' : 'Record buy-in' }}
            </Button>
        </Form>
    </main>
</template>

<style scoped></style>
