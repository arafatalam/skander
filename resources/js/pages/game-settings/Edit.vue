<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GameSettingsForm from '@/components/GameSettingsForm.vue';
import Heading from '@/components/Heading.vue';
import { standalone } from '@/routes/game-settings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Game settings',
                href: standalone(),
            },
        ],
    },
});

defineProps<{
    settings: {
        default_min_buy_in: number;
        default_max_buy_in: number;
    };
}>();
</script>

<template>
    <Head title="Game settings" />

    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading
            title="Game settings"
            description="Set the default minimum and maximum buy-in for new games."
        />

        <div class="w-full max-w-xl">
            <GameSettingsForm :settings="settings" />
        </div>
    </main>
</template>
