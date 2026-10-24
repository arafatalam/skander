<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CorrectionHistory, {
    type Revision,
} from '@/components/CorrectionHistory.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
type Value = { label: string; value: string };
defineProps<{
    title: string;
    back_url: string;
    record_id: number;
    source: 'manual' | 'automatic' | null;
    original: {
        actor: string | null;
        recorded_at: string | null;
        values: Value[];
    };
    current: Value[];
    revisions: Revision[];
}>();
const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Moncton',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>
<template>
    <Head title="Correction history" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
        <Heading title="Correction history" :description="title" />
        <Button variant="outline" as-child class="self-start"
            ><Link :href="back_url">Back to history</Link></Button
        >
        <p class="text-sm text-muted-foreground">
            Record #{{ record_id
            }}<span v-if="source"> · Originally {{ source }}</span
            >. Only the current values count toward totals and balances.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
            <section
                v-for="section in ['original', 'current'] as const"
                :key="section"
                class="min-w-0 rounded-xl border p-4 sm:p-6"
            >
                <h2 class="mb-3 text-lg font-semibold">
                    {{
                        section === 'original'
                            ? 'Originally recorded'
                            : 'Current values'
                    }}
                </h2>
                <p
                    v-if="section === 'original'"
                    class="mb-4 text-sm break-words"
                >
                    {{ original.actor ?? 'Not recorded'
                    }}<template v-if="original.recorded_at">
                        ·
                        {{ dateTime(original.recorded_at) }} (Moncton)</template
                    >
                </p>
                <dl class="space-y-3">
                    <div
                        v-for="item in section === 'original'
                            ? original.values
                            : current"
                        :key="item.label"
                    >
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
        <CorrectionHistory :revisions="revisions" />
    </main>
</template>
