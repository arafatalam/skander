<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    players: {
        data: {
            id: number;
            name: string;
            phone: string | null;
            email: string | null;
            is_active: boolean;
            balance: number;
            show_url: string;
        }[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };

    search: string;
    owing: string;
}>();

const search = ref(props.search);
const owing = ref(props.owing);
watch(
    () => props.owing,
    (value) => {
        owing.value = value;
    },
);
watch(
    () => props.search,
    (value) => {
        search.value = value;
    },
);
const money = (value: number) => `$${Math.abs(value).toLocaleString('en-US')}`;
function changeDirection(value: unknown) {
    owing.value = String(value);
    router.get(
        PlayerController.index.url(),
        { search: props.search, owing: owing.value },
        { preserveState: true, preserveScroll: true },
    );
}

function submitSearch() {
    router.get(
        PlayerController.index.url(),
        { search: search.value, owing: owing.value },
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
        { owing: owing.value },
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

        <div class="w-full max-w-xs space-y-2">
            <Label for="owing-direction">Owing direction</Label>
            <Select :model-value="owing" @update:model-value="changeDirection">
                <SelectTrigger id="owing-direction"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All players</SelectItem>
                    <SelectItem value="collect"
                        >Players owe the house</SelectItem
                    >
                    <SelectItem value="pay">House owes players</SelectItem>
                </SelectContent>
            </Select>
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
                        <th class="px-4 py-3 font-medium">Balance</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="player in players.data"
                        :key="player.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="player.show_url"
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
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ money(player.balance) }}
                            <span class="block text-xs text-muted-foreground">{{
                                player.balance < 0
                                    ? 'Player owes'
                                    : player.balance > 0
                                      ? 'House owes'
                                      : 'Settled'
                            }}</span>
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
                    <tr v-if="players.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            <span v-if="search"
                                >No players match "{{ search }}".</span
                            >
                            <span v-else-if="owing === 'collect'"
                                >No players owe the house.</span
                            >
                            <span v-else-if="owing === 'pay'"
                                >The house owes no players.</span
                            >
                            <span v-else>No players have been added yet.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
        <nav
            aria-label="Player pages"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Page {{ players.current_page }} of {{ players.last_page }} ·
                {{ players.total }} players
            </p>
            <div class="flex gap-2">
                <Button v-if="players.prev_page_url" variant="outline" as-child
                    ><Link :href="players.prev_page_url">Previous</Link></Button
                ><Button v-else variant="outline" disabled>Previous</Button>
                <Button v-if="players.next_page_url" variant="outline" as-child
                    ><Link :href="players.next_page_url">Next</Link></Button
                ><Button v-else variant="outline" disabled>Next</Button>
            </div>
        </nav>
    </main>
</template>

<style scoped></style>
