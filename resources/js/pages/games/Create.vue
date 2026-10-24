<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

defineProps<{
    gameDate: string;
    gameName: string;
    defaultLimits: {
        min_buy_in: number;
        max_buy_in: number;
    };
}>();

const confirmationOpen = ref(false);
const useCustomLimits = ref(false);
</script>

<template>
    <Head title="Create game" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Create game"
            description="Start a new game for today."
        />

        <Dialog v-model:open="confirmationOpen">
            <DialogTrigger as-child>
                <Button type="button" class="w-fit"> Create game </Button>
            </DialogTrigger>

            <DialogContent class="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Confirm game creation</DialogTitle>

                    <DialogDescription>
                        Create game {{ gameName }} ?
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="GameSessionController.store.form()"
                    novalidate
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                    @success="confirmationOpen = false"
                >
                    <input type="hidden" name="game_date" :value="gameDate" />

                    <input type="hidden" name="game_name" :value="gameName" />
                    <input
                        type="hidden"
                        name="confirmed_min_buy_in"
                        :value="defaultLimits.min_buy_in"
                    />
                    <input
                        type="hidden"
                        name="confirmed_max_buy_in"
                        :value="defaultLimits.max_buy_in"
                    />
                    <input
                        type="hidden"
                        name="use_custom_limits"
                        :value="useCustomLimits ? '1' : '0'"
                    />

                    <InputError :message="errors.game_date" />
                    <InputError :message="errors.game_name" />
                    <InputError :message="errors.default_limits" />
                    <InputError :message="errors.confirmed_min_buy_in" />
                    <InputError :message="errors.confirmed_max_buy_in" />

                    <div class="rounded-md border p-4 text-sm">
                        <p class="font-medium">Default buy-in limits</p>
                        <dl class="mt-2 grid grid-cols-2 gap-2">
                            <dt>Minimum</dt>
                            <dd class="text-right">
                                ${{ defaultLimits.min_buy_in }}
                            </dd>
                            <dt>Maximum</dt>
                            <dd class="text-right">
                                ${{ defaultLimits.max_buy_in }}
                            </dd>
                        </dl>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="use_custom_limits"
                                v-model="useCustomLimits"
                                :disabled="processing"
                            />
                            <Label for="use_custom_limits">
                                Use different buy-in limits
                            </Label>
                        </div>
                        <InputError :message="errors.use_custom_limits" />
                    </div>

                    <div
                        v-if="useCustomLimits"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <div class="grid gap-2">
                            <Label for="min_buy_in">Minimum buy-in ($)</Label>
                            <Input
                                id="min_buy_in"
                                name="min_buy_in"
                                type="number"
                                :default-value="defaultLimits.min_buy_in"
                                :disabled="processing"
                            />
                            <InputError :message="errors.min_buy_in" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="max_buy_in">Maximum buy-in ($)</Label>
                            <Input
                                id="max_buy_in"
                                name="max_buy_in"
                                type="number"
                                :default-value="defaultLimits.max_buy_in"
                                :disabled="processing"
                            />
                            <InputError :message="errors.max_buy_in" />
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="secondary"
                                :disabled="processing"
                            >
                                Cancel
                            </Button>
                        </DialogClose>

                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Creating...' : 'Confirm' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </main>
</template>
