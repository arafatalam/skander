<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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

defineProps<{
    payment: {
        id: number;
        player_name: string;
        amount: number;
        direction: string;
        paid_at: string;
        note: string | null;
        revision: number;
    };
    submissionId: string;
    today: string;
    updateUrl: string;
    backUrl: string;
}>();
</script>

<template>
    <Head title="Correct payment" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading title="Correct payment" :description="payment.player_name" />
        <p class="max-w-xl text-sm text-muted-foreground">
            The payment date applies to every game on that date and all later
            dates. The original values and your reason will remain in correction
            history.
        </p>
        <Form
            :action="updateUrl"
            method="patch"
            v-slot="{ errors, processing }"
            class="max-w-xl space-y-6"
            novalidate
        >
            <input type="hidden" name="submission_id" :value="submissionId" />
            <input type="hidden" name="revision" :value="payment.revision" />
            <InputError :message="errors.submission_id" />
            <InputError :message="errors.revision" />
            <InputError :message="errors.balance" />
            <fieldset :disabled="processing" class="space-y-6">
                <div class="grid gap-2">
                    <Label for="amount">Amount ($)</Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="number"
                        min="1"
                        max="2147483647"
                        step="1"
                        :default-value="payment.amount"
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="direction">Payment direction</Label>
                    <Select
                        name="direction"
                        :default-value="payment.direction"
                        :disabled="processing"
                    >
                        <SelectTrigger id="direction" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="house_pays_player"
                                >House pays player</SelectItem
                            >
                            <SelectItem value="house_collects_from_player"
                                >House collects from player</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.direction" />
                </div>
                <div class="grid gap-2">
                    <Label for="paid_at">Actual payment date</Label>
                    <Input
                        id="paid_at"
                        name="paid_at"
                        type="date"
                        :max="today"
                        :default-value="payment.paid_at"
                    />
                    <InputError :message="errors.paid_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="note">Payment note (optional)</Label>
                    <Input
                        id="note"
                        name="note"
                        :default-value="payment.note ?? ''"
                        maxlength="2000"
                    />
                    <InputError :message="errors.note" />
                </div>
                <div class="grid gap-2">
                    <Label for="reason">Reason for correction (required)</Label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="3"
                        maxlength="2000"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.reason" />
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :disabled="processing">{{
                        processing ? 'Saving...' : 'Save correction'
                    }}</Button>
                    <Button as-child variant="outline"
                        ><Link :href="backUrl">Cancel</Link></Button
                    >
                </div>
            </fieldset>
        </Form>
    </main>
</template>
