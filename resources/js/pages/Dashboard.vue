<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Poker Home Game Record Book" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <header class="space-y-2">
            <p class="text-sm text-muted-foreground">
                Poker Home Game Record Book
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome, {{ $page.props.auth.user.name }}
            </h1>
            <p class="text-muted-foreground">
                Keep your home game player and session records in one place.
            </p>
        </header>

        <section class="rounded-xl border border-sidebar-border/70 bg-card p-5">
            <h2 class="font-medium">Getting started</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Player and game records will appear here as you add them.
            </p>
        </section>
    </main>
</template>
