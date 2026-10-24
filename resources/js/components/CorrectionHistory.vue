<script setup lang="ts">
type Value = { label: string; value: string };
export type Revision = {
    id: number;
    revision: number;
    actor: string | null;
    corrected_at: string;
    reason: string;
    before: Value[];
    after: Value[];
};
defineProps<{ revisions: Revision[] }>();
const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Moncton',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>
<template>
    <section aria-labelledby="corrections-heading" class="space-y-4">
        <h2 id="corrections-heading" class="text-lg font-semibold">
            Corrections
        </h2>
        <p v-if="revisions.length === 0" class="text-sm text-muted-foreground">
            No corrections recorded.
        </p>
        <article
            v-for="revision in revisions"
            :key="revision.id"
            class="min-w-0 space-y-4 rounded-xl border p-4 sm:p-6"
        >
            <h3 class="font-semibold">Revision {{ revision.revision }}</h3>
            <p class="text-sm break-words">
                {{ revision.actor ?? 'Unknown Admin' }} ·
                {{ dateTime(revision.corrected_at) }} (Moncton)
            </p>
            <p class="break-words whitespace-pre-wrap">
                <span class="font-medium">Reason:</span> {{ revision.reason }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <section
                    v-for="side in ['before', 'after'] as const"
                    :key="side"
                    class="min-w-0"
                >
                    <h4 class="mb-2 font-medium">
                        {{ side === 'before' ? 'Before' : 'After' }}
                    </h4>
                    <dl class="space-y-2">
                        <div v-for="item in revision[side]" :key="item.label">
                            <dt class="text-sm text-muted-foreground">
                                {{ item.label }}
                            </dt>
                            <dd class="break-words whitespace-pre-wrap">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </article>
    </section>
</template>
