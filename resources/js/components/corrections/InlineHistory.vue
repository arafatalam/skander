<script setup lang="ts">
import CorrectionHistory, {
    type Revision,
} from '@/components/CorrectionHistory.vue';

export type History = {
    label?: string;
    original: {
        actor: string | null;
        recorded_at: string | null;
        values: { label: string; value: string }[];
    };
    revisions: Revision[];
};
export type Addition = {
    id: number;
    kind: string;
    actor: string;
    recorded_at: string;
    reason: string;
};
defineProps<{ histories: History[]; additions: Addition[] }>();
const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Moncton',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <div class="space-y-6 border-t pt-4">
        <article
            v-for="addition in additions"
            :key="addition.id"
            class="space-y-2 rounded-md border p-4"
        >
            <h4 class="font-medium">
                Historical addition · {{ addition.kind }}
            </h4>
            <p class="text-sm">
                {{ addition.actor }} ·
                {{ dateTime(addition.recorded_at) }} (Moncton)
            </p>
            <p class="break-words whitespace-pre-wrap">{{ addition.reason }}</p>
        </article>
        <section
            v-for="(history, index) in histories"
            :key="index"
            class="space-y-4"
        >
            <h4 class="font-medium">
                {{
                    history.label
                        ? `${history.label} · Originally recorded`
                        : 'Originally recorded'
                }}
            </h4>
            <p class="text-sm text-muted-foreground">
                {{ history.original.actor ?? 'Not recorded'
                }}<template v-if="history.original.recorded_at">
                    ·
                    {{ dateTime(history.original.recorded_at) }}
                    (Moncton)</template
                >
            </p>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="value in history.original.values"
                    :key="value.label"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ value.label }}
                    </dt>
                    <dd class="break-words whitespace-pre-wrap">
                        {{ value.value }}
                    </dd>
                </div>
            </dl>
            <CorrectionHistory :revisions="history.revisions" />
        </section>
    </div>
</template>
