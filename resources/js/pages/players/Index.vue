<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineProps<{
    players: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        is_active: boolean;
    }[];
}>();
</script>

<template>
    <Head title="Players" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Players"
                description="View player profiles and contact details."
            />

            <Button as-child>
                <Link :href="PlayerController.create()">Add player</Link>
            </Button>
        </div>
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Phone</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="player in players"
                        :key="player.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">{{ player.name }}</td>
                        <td class="px-4 py-3">
                            {{ player.phone || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ player.email || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge v-if="!player.is_active" variant="secondary">
                                Inactive
                            </Badge>
                            <Badge v-else variant="secondary">Active</Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</template>

<style scoped></style>
