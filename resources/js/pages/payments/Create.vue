<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
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
    players: {
        id: number;
        name: string;
        balance: number;
    }[];
    defaultPaidAt: string;
    submissionId: string;
}>();

const selectedPlayerId = ref('');
const paidAt = ref(props.defaultPaidAt);
const amount = ref<number | string>('');
const note = ref('');
const submissionId = ref(props.submissionId);

const selectedPlayer = computed(() =>
    props.players.find(
        (player) => String(player.id) === selectedPlayerId.value,
    ),
);

const direction = computed(() => {
    const balance = selectedPlayer.value?.balance;
    if (balance === undefined || balance === 0) return '';

    return balance > 0 ? 'house_pays_player' : 'house_collects_from_player';
});

const directionLabel = computed(() => {
    if (direction.value === 'house_pays_player') return 'House pays player';
    if (direction.value === 'house_collects_from_player')
        return 'House collects from player';

    return '';
});

function balanceLabel(balance: number): string {
    const amount = Math.abs(balance).toLocaleString('en-US');

    return balance > 0
        ? `House owes player $${amount}`
        : `Player owes house $${amount}`;
}

function resetPaymentFields(): void {
    selectedPlayerId.value = '';
    amount.value = '';
    paidAt.value = props.defaultPaidAt;
    note.value = '';
    submissionId.value = props.submissionId;
}
</script>

<template>
    <Head title="Record payment" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Record payment"
            description="Record money paid to or collected from a player."
        />

        <p v-if="players.length === 0" class="text-sm text-muted-foreground">
            No players have an outstanding balance.
        </p>

        <Form
            v-else
            v-bind="PaymentController.store.form()"
            v-slot="{ errors, processing }"
            novalidate
            class="max-w-xl space-y-6"
            @success="resetPaymentFields"
        >
            <input type="hidden" name="submission_id" :value="submissionId" />

            <InputError :message="errors.submission_id" />

            <fieldset :disabled="processing" class="space-y-6">
                <div class="grid gap-2">
                    <Label for="player_id">Player</Label>

                    <Select
                        v-model="selectedPlayerId"
                        name="player_id"
                        :disabled="processing"
                    >
                        <SelectTrigger id="player_id" class="w-full">
                            <SelectValue placeholder="Choose a player" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem
                                v-for="player in players"
                                :key="player.id"
                                :value="String(player.id)"
                            >
                                {{ player.name }} —
                                {{ balanceLabel(player.balance) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.player_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="direction">Payment direction</Label>
                    <Input
                        id="direction"
                        :model-value="directionLabel"
                        placeholder="Choose a player first"
                        readonly
                        aria-describedby="direction-help"
                    />
                    <input type="hidden" name="direction" :value="direction" />
                    <p
                        id="direction-help"
                        class="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {{
                            directionLabel ||
                            'Payment direction follows the selected player’s balance.'
                        }}
                    </p>
                    <InputError :message="errors.direction" />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Amount ($)</Label>
                    <Input
                        id="amount"
                        v-model="amount"
                        name="amount"
                        type="number"
                        min="1"
                        max="2147483647"
                        step="1"
                        aria-describedby="amount-help"
                    />
                    <p id="amount-help" class="text-sm text-muted-foreground">
                        Enter a positive whole-dollar amount. Payments may be
                        partial, full, or greater than the outstanding balance.
                    </p>
                    <InputError :message="errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="paid_at">Payment date</Label>
                    <Input
                        id="paid_at"
                        v-model="paidAt"
                        name="paid_at"
                        type="date"
                        :max="defaultPaidAt"
                    />
                    <p class="text-sm text-muted-foreground">
                        Defaults to today. Change it if the payment happened
                        earlier.
                    </p>
                    <InputError :message="errors.paid_at" />
                </div>

                <div class="grid gap-2">
                    <Label for="note">Note (optional)</Label>
                    <textarea
                        id="note"
                        v-model="note"
                        name="note"
                        rows="3"
                        maxlength="2000"
                        aria-describedby="note-help"
                        class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                    />
                    <p id="note-help" class="text-sm text-muted-foreground">
                        Up to 2,000 characters.
                    </p>
                    <InputError :message="errors.note" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing || !selectedPlayerId || !direction"
                >
                    {{ processing ? 'Recording...' : 'Record payment' }}
                </Button>
            </fieldset>
        </Form>
    </main>
</template>
