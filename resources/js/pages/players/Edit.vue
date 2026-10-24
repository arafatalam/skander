<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    player: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
    };
}>();
</script>

<template>
    <Head :title="`Edit ${player.name}`" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading title="Edit player profile" :description="player.name" />

        <Form
            v-bind="PlayerController.update.form(player.id)"
            novalidate
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" :default-value="player.name" />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Phone number</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    :default-value="player.phone ?? ''"
                />
                <InputError :message="errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="player.email ?? ''"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button :disabled="processing">
                    {{ processing ? 'Saving...' : 'Save changes' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="PlayerController.show(player.id)">Cancel</Link>
                </Button>
            </div>
        </Form>
    </main>
</template>
