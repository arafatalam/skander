<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref } from 'vue';

const props = defineProps<{
    players: {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        is_active: boolean;
    }[];

    search: string;
}>();

const search = ref(props.search);

function submitSearch() {
    router.get(
        PlayerController.index.url(),
        { search: search.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearSearch() {
    search.value = '';

    router.get(
        PlayerController.index.url(),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}
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

        <form
            class="flex w-full max-w-lg gap-2"
            role="search"
            @submit.prevent="submitSearch"
        >
            <Input
                v-model="search"
                type="search"
                aria-label="Search players"
                placeholder="Search by name, phone, or email"
            />
            <Button type="submit" variant="outline">Search</Button>

            <Button
                type="button"
                variant="outline"
                :disabled="!search"
                @click="clearSearch"
            >
                Clear
            </Button>
        </form>

        <section
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Phone</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="player in players"
                        :key="player.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="PlayerController.show(player.id)"
                                class="rounded-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                            >
                                {{ player.name }}
                            </Link>
                        </td>
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
                        <td class="px-4 py-3">
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="PlayerController.edit(player.id)"
                                    :aria-label="`Edit ${player.name}`"
                                >
                                    Edit player
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="players.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            <span v-if="search"
                                >No players match "{{ search }}".</span
                            >
                            <span v-else>No players have been added yet.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</template>

<style scoped></style>
