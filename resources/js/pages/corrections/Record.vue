<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import HistoricalVisits, {
    type HistoricalVisit,
} from '@/components/corrections/HistoricalVisits.vue';
import InlineHistory, {
    type Addition,
    type History,
} from '@/components/corrections/InlineHistory.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type RecordEntry = {
    key: string;
    kind: string;
    target_id: number;
    title: string;
    subtitle: string;
    visit_number?: number;
    values: { label: string; value: string }[];
    inputs: Record<string, string | number | null>;
    revision: number;
    histories: History[];
    additions: Addition[];
};
const props = defineProps<{
    selection: {
        tab: string;
        game_id: number | null;
        player_id: number | null;
        mode: string;
    };
    games: { id: number; game_name: string; status: string }[];
    players: { id: number; name: string; is_active: boolean }[];
    records: RecordEntry[];
    visits: {
        id: number;
        visit_number: number;
        cashout_id: number | null;
        automatic: boolean;
    }[];
    additions: Addition[];
    sequenceVersion: string;
    submissionId: string;
    today: string;
    indexUrl: string;
    storeUrl: string;
    openRecordId: number | null;
    result: { message: string; warnings: string[] } | null;
}>();
const tabs = [
    { key: 'buy-ins', label: 'Buy-in corrections' },
    { key: 'cashouts', label: 'Cashout corrections' },
    { key: 'payments', label: 'Payment corrections' },
    { key: 'totals', label: 'Game totals' },
    { key: 'missing', label: 'Add missing activity' },
];
const modes = [
    { key: 'missing_player', label: 'Forgotten player' },
    { key: 'missing_visit', label: 'Forgotten visit' },
    { key: 'missing_buy_in', label: 'Missing buy-in' },
    { key: 'missing_cashout', label: 'Missing cashout' },
    { key: 'missing_payment', label: 'Missing payment' },
];
const isMissing = computed(() => props.selection.tab === 'missing');
const needsGame = computed(
    () =>
        props.selection.tab !== 'payments' &&
        !(isMissing.value && props.selection.mode === 'missing_payment'),
);
const needsPlayer = computed(() => props.selection.tab !== 'totals');
const ready = computed(
    () =>
        (!needsGame.value || !!props.selection.game_id) &&
        (!needsPlayer.value || !!props.selection.player_id),
);
const editing = ref<string | null>(null);
const editTitle = ref('');
const expanded = ref<string | null>(null);
const discardOpen = ref(false);
let pendingChange: (() => void) | null = null;
let allowNavigation = false;

const emptyDraft = () => ({
    kind: '',
    game_id: null as number | null,
    player_id: null as number | null,
    target_id: null as number | null,
    revision: 0,
    submission_id: props.submissionId,
    sequence_version: props.sequenceVersion,
    cash_amount: 0 as number | string,
    credit_amount: 0 as number | string,
    ending_chips: 0 as number | string,
    cash_paid: 0 as number | string,
    amount: '' as number | string,
    direction: 'house_collects_from_player',
    paid_at: props.today,
    note: '',
    reason: '',
    visit_id: '' as number | string,
    position: 1 as number | string,
    visits: [
        {
            buy_ins: [{ cash_amount: 0, credit_amount: 0 }],
            ending_chips: '',
            cash_paid: '',
        },
    ] as HistoricalVisit[],
});
const form = useForm(emptyDraft());
const hasChanges = computed(() => editing.value !== null && form.isDirty);
const selectedVisit = computed(() =>
    props.visits.find((visit) => visit.id === Number(form.visit_id)),
);
const existingCashout = computed(
    () => form.kind === 'missing_cashout' && !!selectedVisit.value?.cashout_id,
);
const moneyFields = computed(() => {
    if (['buy_in', 'missing_buy_in'].includes(form.kind))
        return [
            { key: 'cash_amount' as const, label: 'Cash ($)' },
            { key: 'credit_amount' as const, label: 'Credit ($)' },
        ];
    if (['cashout', 'missing_cashout'].includes(form.kind))
        return [
            { key: 'ending_chips' as const, label: 'Cashout ($)' },
            { key: 'cash_paid' as const, label: 'Cash paid at cashout ($)' },
        ];
    return [{ key: 'amount' as const, label: 'Amount ($)' }];
});
const isPayment = computed(() =>
    ['payment', 'missing_payment'].includes(form.kind),
);
const addingVisits = computed(() =>
    ['missing_player', 'missing_visit'].includes(form.kind),
);
const resetEditor = () => {
    form.reset();
    form.clearErrors();
    editing.value = null;
};
const requestChange = (change: () => void) => {
    if (form.processing) return;
    if (hasChanges.value) {
        pendingChange = change;
        discardOpen.value = true;
    } else change();
};
const navigate = (selection: Record<string, string | number | null>) => {
    resetEditor();
    expanded.value = null;
    allowNavigation = true;
    router.get(props.indexUrl, selection, {
        preserveState: true,
        onFinish: () => {
            allowNavigation = false;
        },
    });
};
const changeTab = (tab: string) => requestChange(() => navigate({ tab }));
const changeGame = (value: unknown) =>
    requestChange(() =>
        navigate({
            tab: props.selection.tab,
            mode: props.selection.mode,
            game_id: Number(value),
        }),
    );
const changePlayer = (value: unknown) =>
    requestChange(() =>
        navigate({ ...props.selection, player_id: Number(value) }),
    );
const changeMode = (value: unknown) =>
    requestChange(() => navigate({ tab: 'missing', mode: String(value) }));
const correctExistingCashout = () => {
    const selection = {
        tab: 'cashouts',
        game_id: props.selection.game_id,
        player_id: props.selection.player_id,
        record_id: selectedVisit.value?.cashout_id ?? null,
    };
    requestChange(() => navigate(selection));
};
const openForm = (record?: RecordEntry) =>
    requestChange(() => {
        const draft = {
            ...emptyDraft(),
            game_id: props.selection.game_id,
            player_id: props.selection.player_id,
        };
        if (record) {
            Object.assign(draft, record.inputs, {
                kind: record.kind,
                target_id: record.target_id,
                revision: record.revision,
            });
            draft.note ??= '';
            draft.amount ??= '';
            editing.value = record.key;
            editTitle.value = record.title;
        } else {
            draft.kind = props.selection.mode;
            draft.position = String(props.visits.length + 1);
            editing.value = 'missing';
            editTitle.value =
                modes.find((mode) => mode.key === draft.kind)?.label ??
                'Missing activity';
        }
        form.defaults(draft);
        form.reset();
        form.clearErrors();
        void nextTick(() =>
            document
                .getElementById('correction-form')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
        );
    });
const save = () => {
    allowNavigation = true;
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            resetEditor();
            expanded.value = null;
        },
        onFinish: () => {
            allowNavigation = false;
        },
    });
};
const discard = () => {
    discardOpen.value = false;
    resetEditor();
    const change = pendingChange;
    pendingChange = null;
    change?.();
};
const removeBefore = router.on('before', (event) => {
    if (allowNavigation || !hasChanges.value) return;
    event.preventDefault();
    const { url, ...options } = event.detail.visit;
    pendingChange = () => {
        allowNavigation = true;
        router.visit(url, {
            ...options,
            onFinish: () => {
                allowNavigation = false;
            },
        });
    };
    discardOpen.value = true;
});
const beforeUnload = (event: BeforeUnloadEvent) => {
    if (hasChanges.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};
if (typeof window !== 'undefined')
    window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => {
    removeBefore();
    if (typeof window !== 'undefined')
        window.removeEventListener('beforeunload', beforeUnload);
});
watch(
    () => props.openRecordId,
    (id) => {
        if (id) {
            const record = props.records.find(
                (entry) => entry.target_id === Number(id),
            );
            if (record) openForm(record);
        }
    },
    { immediate: true },
);
const recordedTime = (subtitle: string) =>
    subtitle.startsWith('Recorded ')
        ? `Recorded ${new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Moncton', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(subtitle.slice(9)))} (Moncton)`
        : subtitle;
</script>

<template>
    <Head title="Record Correction" />
    <main class="min-w-0 px-4 py-6 sm:px-6">
        <Heading
            title="Record Correction"
            description="Correct recorded activity or add a forgotten record. Every change keeps its explanation and history."
        />
        <div class="flex min-w-0 flex-col lg:flex-row lg:gap-12">
            <aside class="w-full shrink-0 lg:w-48">
                <nav class="flex flex-col gap-1" aria-label="Correction types">
                    <Button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        variant="ghost"
                        class="justify-start"
                        :class="{ 'bg-muted': selection.tab === tab.key }"
                        :aria-current="
                            selection.tab === tab.key ? 'page' : undefined
                        "
                        :disabled="form.processing"
                        @click="changeTab(tab.key)"
                        >{{ tab.label }}</Button
                    >
                </nav>
            </aside>
            <Separator class="my-6 lg:hidden" />
            <section class="min-w-0 flex-1 space-y-6 lg:max-w-3xl">
                <h2 class="text-lg font-semibold">
                    {{ tabs.find((tab) => tab.key === selection.tab)?.label }}
                </h2>
                <div
                    v-if="result"
                    role="status"
                    class="space-y-3 rounded-xl border p-4"
                >
                    <p>{{ result.message }}</p>
                    <p
                        v-for="warning in result.warnings"
                        :key="warning"
                        class="break-words text-amber-800 dark:text-amber-300"
                    >
                        {{ warning }}
                    </p>
                </div>
                <div v-if="isMissing" class="grid gap-2">
                    <Label for="missing-mode">Missing activity</Label>
                    <Select
                        :model-value="selection.mode"
                        :disabled="form.processing"
                        @update:model-value="changeMode"
                    >
                        <SelectTrigger id="missing-mode" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent
                            ><SelectItem
                                v-for="mode in modes"
                                :key="mode.key"
                                :value="mode.key"
                                >{{ mode.label }}</SelectItem
                            ></SelectContent
                        >
                    </Select>
                </div>
                <div v-if="needsGame" class="grid gap-2">
                    <Label for="correction-game">Game</Label>
                    <Select
                        :model-value="
                            selection.game_id ? String(selection.game_id) : ''
                        "
                        :disabled="form.processing"
                        @update:model-value="changeGame"
                    >
                        <SelectTrigger id="correction-game" class="w-full"
                            ><SelectValue placeholder="Choose a game"
                        /></SelectTrigger>
                        <SelectContent
                            ><SelectItem
                                v-for="game in games"
                                :key="game.id"
                                :value="String(game.id)"
                                >{{ game.game_name }} —
                                {{
                                    game.status === 'open' ? 'Open' : 'Closed'
                                }}</SelectItem
                            ></SelectContent
                        >
                    </Select>
                    <p
                        v-if="games.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No games have been recorded.
                    </p>
                </div>
                <div
                    v-if="needsPlayer && (!needsGame || selection.game_id)"
                    class="grid gap-2"
                >
                    <Label for="correction-player">Player</Label>
                    <Select
                        :model-value="
                            selection.player_id
                                ? String(selection.player_id)
                                : ''
                        "
                        :disabled="form.processing || players.length === 0"
                        @update:model-value="changePlayer"
                    >
                        <SelectTrigger id="correction-player" class="w-full"
                            ><SelectValue placeholder="Choose a player"
                        /></SelectTrigger>
                        <SelectContent
                            ><SelectItem
                                v-for="player in players"
                                :key="player.id"
                                :value="String(player.id)"
                                >{{ player.name
                                }}{{
                                    player.is_active ? '' : ' (Inactive)'
                                }}</SelectItem
                            ></SelectContent
                        >
                    </Select>
                    <p
                        v-if="players.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No players are available for this selection.
                    </p>
                </div>

                <template v-if="ready">
                    <p
                        v-if="!isMissing && records.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No records to correct.
                    </p>
                    <div v-if="isMissing" class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Historical activity uses the game date, or the
                            actual payment date. The recording time and your
                            explanation are kept separately. Closed games stay
                            closed.
                        </p>
                        <ol v-if="visits.length" class="space-y-2 text-sm">
                            <li v-for="visit in visits" :key="visit.id">
                                Visit {{ visit.visit_number }} —
                                {{
                                    visit.cashout_id
                                        ? visit.automatic
                                            ? 'Automatic cashout recorded'
                                            : 'Cashout recorded'
                                        : 'No cashout recorded'
                                }}
                            </li>
                        </ol>
                        <Button
                            type="button"
                            :disabled="form.processing"
                            @click="openForm()"
                            >Add missing activity</Button
                        >
                        <details
                            v-if="additions.length"
                            class="rounded-xl border p-4"
                        >
                            <summary class="cursor-pointer text-sm font-medium">
                                View historical additions
                            </summary>
                            <InlineHistory
                                class="mt-4"
                                :histories="[]"
                                :additions="additions"
                            />
                        </details>
                    </div>
                    <template
                        v-for="(record, index) in records"
                        :key="record.key"
                    >
                        <h3
                            v-if="
                                selection.tab === 'buy-ins' &&
                                (index === 0 ||
                                    records[index - 1].visit_number !==
                                        record.visit_number)
                            "
                            class="border-b pb-2 text-lg font-semibold"
                        >
                            Visit {{ record.visit_number }}
                        </h3>
                        <article
                            class="min-w-0 space-y-4 rounded-xl border p-4 sm:p-6"
                        >
                            <h3 class="font-medium">{{ record.title }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ recordedTime(record.subtitle) }}
                            </p>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="value in record.values"
                                    :key="value.label"
                                >
                                    <dt class="text-sm text-muted-foreground">
                                        {{ value.label }}
                                    </dt>
                                    <dd>{{ value.value }}</dd>
                                </div>
                            </dl>
                            <div class="flex flex-wrap gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="form.processing"
                                    @click="openForm(record)"
                                    >Correct</Button
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    :aria-expanded="expanded === record.key"
                                    @click="
                                        expanded =
                                            expanded === record.key
                                                ? null
                                                : record.key
                                    "
                                    >{{
                                        expanded === record.key
                                            ? 'Hide history'
                                            : 'View history'
                                    }}</Button
                                >
                            </div>
                            <InlineHistory
                                v-if="expanded === record.key"
                                :histories="record.histories"
                                :additions="record.additions"
                            />
                        </article>
                    </template>
                </template>

                <form
                    v-if="editing"
                    id="correction-form"
                    class="space-y-6 rounded-xl border p-4 sm:p-6"
                    novalidate
                    @submit.prevent="save"
                >
                    <h3 class="text-lg font-semibold">{{ editTitle }}</h3>
                    <div
                        v-if="Object.keys(form.errors).length"
                        role="alert"
                        class="space-y-2"
                    >
                        <InputError
                            v-for="(error, field) in form.errors"
                            :key="field"
                            :message="error"
                        />
                        <Button
                            v-if="
                                form.errors.revision ||
                                form.errors.sequence_version ||
                                form.errors.submission_id
                            "
                            type="button"
                            variant="outline"
                            @click="
                                requestChange(() => navigate({ ...selection }))
                            "
                            >Reload list</Button
                        >
                    </div>
                    <fieldset :disabled="form.processing" class="space-y-6">
                        <div
                            v-if="
                                ['missing_buy_in', 'missing_cashout'].includes(
                                    form.kind,
                                )
                            "
                            class="grid gap-2"
                        >
                            <Label for="missing-visit">Visit</Label>
                            <Select v-model="form.visit_id"
                                ><SelectTrigger
                                    id="missing-visit"
                                    class="w-full"
                                    ><SelectValue placeholder="Choose a visit"
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectItem
                                        v-for="visit in visits"
                                        :key="visit.id"
                                        :value="String(visit.id)"
                                        >Visit
                                        {{ visit.visit_number }}</SelectItem
                                    ></SelectContent
                                >
                            </Select>
                        </div>
                        <div v-if="existingCashout" class="space-y-3">
                            <p>
                                This visit already has a cashout. Correct that
                                record to preserve its history.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                @click="correctExistingCashout"
                                >Correct existing cashout</Button
                            >
                        </div>
                        <template v-else>
                            <div
                                v-if="form.kind === 'missing_visit'"
                                class="grid gap-2"
                            >
                                <Label for="visit-position"
                                    >Insert visits</Label
                                >
                                <Select v-model="form.position"
                                    ><SelectTrigger
                                        id="visit-position"
                                        class="w-full"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent
                                        ><SelectItem
                                            v-for="visit in visits"
                                            :key="visit.id"
                                            :value="String(visit.visit_number)"
                                            >Before Visit
                                            {{ visit.visit_number }}</SelectItem
                                        ><SelectItem
                                            :value="String(visits.length + 1)"
                                            >After the last visit</SelectItem
                                        ></SelectContent
                                    >
                                </Select>
                                <p class="text-sm text-muted-foreground">
                                    Existing visits are renumbered to show the
                                    new order. Their records and amounts are
                                    preserved.
                                </p>
                            </div>
                            <HistoricalVisits
                                v-if="addingVisits"
                                v-model="form.visits"
                                :disabled="form.processing"
                            />
                            <div v-else class="grid gap-4 sm:grid-cols-2">
                                <div
                                    v-for="field in moneyFields"
                                    :key="field.key"
                                    class="grid gap-2"
                                >
                                    <Label :for="field.key">{{
                                        field.label
                                    }}</Label>
                                    <Input
                                        :id="field.key"
                                        v-model="form[field.key]"
                                        type="number"
                                        :min="isPayment ? 1 : 0"
                                        max="2147483647"
                                        step="1"
                                        required
                                        :aria-invalid="
                                            Boolean(form.errors[field.key])
                                        "
                                    />
                                </div>
                            </div>
                            <div v-if="isPayment" class="space-y-4">
                                <div class="grid gap-2">
                                    <Label for="payment-direction"
                                        >Direction</Label
                                    >
                                    <Select v-model="form.direction"
                                        ><SelectTrigger
                                            id="payment-direction"
                                            class="w-full"
                                            ><SelectValue /></SelectTrigger
                                        ><SelectContent
                                            ><SelectItem
                                                value="house_collects_from_player"
                                                >House collects from
                                                player</SelectItem
                                            ><SelectItem
                                                value="house_pays_player"
                                                >House pays player</SelectItem
                                            ></SelectContent
                                        ></Select
                                    >
                                </div>
                                <div class="grid gap-2">
                                    <Label for="paid_at"
                                        >Actual payment date</Label
                                    ><Input
                                        id="paid_at"
                                        v-model="form.paid_at"
                                        type="date"
                                        :max="today"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="note"
                                        >Payment note (optional)</Label
                                    ><Input
                                        id="note"
                                        v-model="form.note"
                                        maxlength="2000"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="reason"
                                    >Explanation (required)</Label
                                >
                                <textarea
                                    id="reason"
                                    v-model="form.reason"
                                    rows="4"
                                    maxlength="2000"
                                    required
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    :aria-invalid="Boolean(form.errors.reason)"
                                />
                                <p class="text-sm text-muted-foreground">
                                    Any buy-in limit or return mismatch warning
                                    will be saved above your explanation.
                                    Existing return buy-ins stay unchanged.
                                </p>
                            </div>
                        </template>
                        <div class="flex flex-wrap gap-3">
                            <Button
                                v-if="!existingCashout"
                                type="submit"
                                :disabled="form.processing"
                                >{{
                                    form.processing ? 'Saving...' : 'Save'
                                }}</Button
                            >
                            <Button
                                type="button"
                                variant="outline"
                                @click="requestChange(resetEditor)"
                                >Cancel</Button
                            >
                        </div>
                    </fieldset>
                </form>
            </section>
        </div>
        <Dialog v-model:open="discardOpen">
            <DialogContent
                ><DialogHeader
                    ><DialogTitle>Discard unsaved changes?</DialogTitle
                    ><DialogDescription
                        >Your edits have not been saved. Keep editing or discard
                        them to continue.</DialogDescription
                    ></DialogHeader
                >
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        @click="
                            discardOpen = false;
                            pendingChange = null;
                        "
                        >Keep editing</Button
                    ><Button type="button" @click="discard"
                        >Discard changes</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </main>
</template>
