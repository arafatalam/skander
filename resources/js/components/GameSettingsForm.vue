<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import GameSettingController from '@/actions/App/Http/Controllers/GameSettingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    settings: {
        default_min_buy_in: number;
        default_max_buy_in: number;
    };
}>();
</script>

<template>
    <Form
        v-bind="GameSettingController.update.form()"
        novalidate
        class="space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="default_min_buy_in">Minimum buy-in ($)</Label>
            <Input
                id="default_min_buy_in"
                name="default_min_buy_in"
                type="number"
                :default-value="settings.default_min_buy_in"
            />
            <InputError :message="errors.default_min_buy_in" />
        </div>

        <div class="grid gap-2">
            <Label for="default_max_buy_in">Maximum buy-in ($)</Label>
            <Input
                id="default_max_buy_in"
                name="default_max_buy_in"
                type="number"
                :default-value="settings.default_max_buy_in"
            />
            <InputError :message="errors.default_max_buy_in" />
        </div>

        <Button type="submit" :disabled="processing"> Save </Button>
    </Form>
</template>
