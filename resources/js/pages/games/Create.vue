<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
}>();

const confirmationOpen = ref(false);
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

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm game creation</DialogTitle>

                    <DialogDescription>
                        Create game {{ gameName }}?
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="GameSessionController.store.form()"
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                    @success="confirmationOpen = false"
                >
                    <input type="hidden" name="game_date" :value="gameDate" />

                    <input type="hidden" name="game_name" :value="gameName" />

                    <InputError :message="errors.game_date" />
                    <InputError :message="errors.game_name" />

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
