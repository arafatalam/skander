<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Head title="Create player" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Create player"
            description="Add a player profile before recording game activity."
        />

        <Form
            v-bind="PlayerController.store.form()"
            resetOnSuccess
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required maxlength="255" />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Phone number</Label>
                <Input id="phone" name="phone" type="tel" maxlength="255" />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" name="email" maxlength="255" />
                <InputError :message="errors.email" />
            </div>

            <Button :disabled="processing">Create player</Button>
        </Form>
    </main>
</template>

<style scoped></style>
