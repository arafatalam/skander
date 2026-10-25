<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type HistoricalVisit = {
    buy_ins: { cash_amount: number | string; credit_amount: number | string }[];
    ending_chips: number | string;
    cash_paid: number | string;
};
const visits = defineModel<HistoricalVisit[]>({ required: true });
defineProps<{ disabled: boolean }>();
const addVisit = () =>
    visits.value.push({
        buy_ins: [{ cash_amount: 0, credit_amount: 0 }],
        ending_chips: '',
        cash_paid: '',
    });
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-muted-foreground">
            Enter completed visits in the order they happened. Each visit needs
            its buy-ins and cashout. Enter zero chips and zero paid only if that
            is what happened.
        </p>
        <fieldset
            v-for="(visit, index) in visits"
            :key="index"
            :disabled="disabled"
            class="min-w-0 space-y-4 rounded-xl border p-4"
        >
            <legend class="px-2 font-medium">
                Added visit {{ index + 1 }}
            </legend>
            <div
                v-for="(buyIn, purchase) in visit.buy_ins"
                :key="purchase"
                class="space-y-3 border-b pb-4"
            >
                <p class="text-sm font-medium">Buy-in {{ purchase + 1 }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`cash-${index}-${purchase}`"
                            >Cash ($)</Label
                        >
                        <Input
                            :id="`cash-${index}-${purchase}`"
                            v-model="buyIn.cash_amount"
                            type="number"
                            min="0"
                            max="2147483647"
                            step="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`credit-${index}-${purchase}`"
                            >Credit ($)</Label
                        >
                        <Input
                            :id="`credit-${index}-${purchase}`"
                            v-model="buyIn.credit_amount"
                            type="number"
                            min="0"
                            max="2147483647"
                            step="1"
                            required
                        />
                    </div>
                </div>
                <Button
                    v-if="visit.buy_ins.length > 1"
                    type="button"
                    variant="ghost"
                    @click="visit.buy_ins.splice(purchase, 1)"
                    >Remove buy-in {{ purchase + 1 }}</Button
                >
            </div>
            <Button
                type="button"
                variant="outline"
                @click="
                    visit.buy_ins.push({ cash_amount: 0, credit_amount: 0 })
                "
                >Add another buy-in</Button
            >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`chips-${index}`">Ending chips ($)</Label>
                    <Input
                        :id="`chips-${index}`"
                        v-model="visit.ending_chips"
                        type="number"
                        min="0"
                        max="2147483647"
                        step="1"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="`paid-${index}`"
                        >Cash paid at cashout ($)</Label
                    >
                    <Input
                        :id="`paid-${index}`"
                        v-model="visit.cash_paid"
                        type="number"
                        min="0"
                        max="2147483647"
                        step="1"
                        required
                    />
                </div>
            </div>
            <Button
                v-if="visits.length > 1"
                type="button"
                variant="ghost"
                @click="visits.splice(index, 1)"
                >Remove added visit {{ index + 1 }}</Button
            >
        </fieldset>
        <Button
            type="button"
            variant="outline"
            :disabled="disabled"
            @click="addVisit"
            >Add another visit</Button
        >
    </div>
</template>
