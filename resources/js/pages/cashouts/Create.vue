<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CashoutController from '@/actions/App/Http/Controllers/CashoutController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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

const props = defineProps<{
    games: {
        id: number;
        game_name: string;
    }[];
    participants: {
        game_session_id: number;
        game_name: string;
        player_id: number;
        player_name: string;
        expected_visit_id: number;
        visit_number: number;
    }[];
}>();

const selectedGameId = ref(
    props.games.length === 1 ? String(props.games[0].id) : '',
);

const selectedVisitId = ref('');

watch(selectedGameId, () => {
    selectedVisitId.value = '';
});

const availableParticipants = computed(() => {
    return props.participants.filter(
        (participant) =>
            String(participant.game_session_id) === selectedGameId.value,
    );
});

const selectedParticipant = computed(() => {
    return availableParticipants.value.find(
        (participant) =>
            String(participant.expected_visit_id) === selectedVisitId.value,
    );
});
</script>

<template>
    <Head title="Record cashout" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Record cashout"
            description="Choose a player and game, then enter the cashout amount and cash paid."
        />

        <Form
            v-bind="CashoutController.store.form()"
            v-slot="{ errors, processing }"
            novalidate
            class="max-w-xl space-y-6"
        >
            <input
                type="hidden"
                name="game_session_id"
                :value="selectedGameId"
            />
            <input
                type="hidden"
                name="player_id"
                :value="selectedParticipant?.player_id ?? ''"
            />
            <input
                type="hidden"
                name="expected_visit_id"
                :value="selectedParticipant?.expected_visit_id ?? ''"
            />

            <div class="grid gap-2">
                <Label for="cashout_game">Game</Label>

                <Select
                    v-model="selectedGameId"
                    :disabled="processing || games.length === 0"
                >
                    <SelectTrigger id="cashout_game" class="w-full">
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

                <p
                    v-if="games.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No open games are available.
                </p>
                <InputError :message="errors.game_session_id" />
            </div>

            <div v-if="selectedGameId" class="grid gap-2">
                <Label for="cashout_visit">Player</Label>

                <Select
                    v-model="selectedVisitId"
                    :disabled="processing || availableParticipants.length === 0"
                >
                    <SelectTrigger id="cashout_visit" class="w-full">
                        <SelectValue placeholder="Choose a player" />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem
                            v-for="participant in availableParticipants"
                            :key="participant.expected_visit_id"
                            :value="String(participant.expected_visit_id)"
                        >
                            {{ participant.player_name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <p
                    v-if="selectedParticipant"
                    class="text-sm text-muted-foreground"
                >
                    Visit {{ selectedParticipant.visit_number }}
                </p>
                <p
                    v-if="availableParticipants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No players are ready to cash out in this game.
                </p>

                <InputError :message="errors.player_id" />
                <InputError :message="errors.expected_visit_id" />
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="ending_chips">Cashout ($)</Label>

                    <Input
                        id="ending_chips"
                        name="ending_chips"
                        type="number"
                        min="0"
                        max="2147483647"
                        step="1"
                        required
                        :readonly="processing"
                        aria-describedby="ending_chips_help ending_chips_error"
                        :aria-invalid="Boolean(errors.ending_chips)"
                    />

                    <p
                        id="ending_chips_help"
                        class="text-sm text-muted-foreground"
                    >
                        Total value of the chips the player finishes with.
                    </p>
                    <InputError
                        id="ending_chips_error"
                        :message="errors.ending_chips"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="cash_paid">Cash paid ($)</Label>

                    <Input
                        id="cash_paid"
                        name="cash_paid"
                        type="number"
                        min="0"
                        max="2147483647"
                        step="1"
                        default-value="0"
                        required
                        :readonly="processing"
                        aria-describedby="cash_paid_help cash_paid_error"
                        :aria-invalid="Boolean(errors.cash_paid)"
                    />

                    <p
                        id="cash_paid_help"
                        class="text-sm text-muted-foreground"
                    >
                        Cash the house pays now. Enter 0 if no cash is paid.
                    </p>
                    <InputError
                        id="cash_paid_error"
                        :message="errors.cash_paid"
                    />
                </div>
            </div>

            <Button
                type="submit"
                :disabled="processing || !selectedParticipant"
            >
                {{ processing ? 'Saving...' : 'Record cashout' }}
            </Button>
        </Form>
    </main>
</template>
