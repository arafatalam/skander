<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GameSettingsForm from '@/components/GameSettingsForm.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/game-settings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Game settings',
                href: edit(),
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

    <h1 class="sr-only">Game settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Default buy-in limits"
            description="Set the default minimum and maximum buy-in for new games."
        />

        <GameSettingsForm :settings="settings" />
    </div>
</template>
