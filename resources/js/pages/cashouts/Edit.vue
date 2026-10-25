<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    cashout: {
        id: number;
        player_name: string;
        game_name: string;
        visit_number: number;
        ending_chips: number;
        cash_paid: number;
        creation_method: 'manual' | 'automatic';
        revision: number;
    };
    submissionId: string;
    updateUrl: string;
    backUrl: string;
}>();
</script>

<template>
    <Head title="Correct cashout" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading title="Correct cashout" :description="cashout.player_name" />

        <div class="max-w-xl space-y-2 text-sm text-muted-foreground">
            <p class="break-words">
                {{ cashout.game_name }} · Visit {{ cashout.visit_number }}
            </p>
            <p v-if="cashout.creation_method === 'automatic'">
                This cashout was recorded automatically when the game closed.
            </p>
            <p>
                Correct the cashout amount and cash paid at this cashout. The
                original values and your reason will remain in correction
                history. A closed game will stay closed.
            </p>
        </div>

        <Form
            :action="updateUrl"
            method="patch"
            v-slot="{ errors, processing }"
            class="max-w-xl space-y-6"
            novalidate
        >
            <input type="hidden" name="submission_id" :value="submissionId" />
            <input type="hidden" name="revision" :value="cashout.revision" />

            <InputError :message="errors.submission_id" />
            <InputError :message="errors.revision" />
            <InputError :message="errors.balance" />
            <InputError :message="errors.income" />

            <fieldset :disabled="processing" class="space-y-6">
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
                            :default-value="cashout.ending_chips"
                            aria-describedby="ending_chips_help ending_chips_error"
                            :aria-invalid="Boolean(errors.ending_chips)"
                        />
                        <p
                            id="ending_chips_help"
                            class="text-sm text-muted-foreground"
                        >
                            Total value of the chips the player finished with.
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
                            required
                            :default-value="cashout.cash_paid"
                            aria-describedby="cash_paid_help cash_paid_error"
                            :aria-invalid="Boolean(errors.cash_paid)"
                        />
                        <p
                            id="cash_paid_help"
                            class="text-sm text-muted-foreground"
                        >
                            Cash the house paid at this cashout. Enter 0 if no
                            cash was paid. Record later payments separately.
                        </p>
                        <InputError
                            id="cash_paid_error"
                            :message="errors.cash_paid"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="reason">Reason for correction (required)</Label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="3"
                        maxlength="2000"
                        required
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        aria-describedby="reason_help reason_error"
                        :aria-invalid="Boolean(errors.reason)"
                    />
                    <p id="reason_help" class="text-sm text-muted-foreground">
                        If this correction conflicts with an already-recorded
                        return buy-in, the buy-in stays unchanged. A warning
                        will appear above your explanation in the saved history.
                    </p>
                    <InputError id="reason_error" :message="errors.reason" />
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Saving...' : 'Save correction' }}
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="backUrl">Cancel</Link>
                    </Button>
                </div>
            </fieldset>
        </Form>
    </main>
</template>
