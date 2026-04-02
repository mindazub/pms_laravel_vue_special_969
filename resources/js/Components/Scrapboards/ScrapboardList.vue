<script setup>
defineProps({
    scrapboards: {
        type: Array,
        default: () => [],
    },
    selectedScrapboardId: {
        type: [Number, String, null],
        default: null,
    },
});

defineEmits(['select', 'create', 'rename', 'delete']);
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Boards</h3>
                <p class="mt-1 text-sm text-slate-500">Manage your saved scrapboards and open them quickly.</p>
            </div>
            <button
                type="button"
                class="rounded-md border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800"
                @click="$emit('create')"
            >
                New board
            </button>
        </div>

        <div class="mt-4 space-y-2">
            <div
                v-for="scrapboard in scrapboards"
                :key="scrapboard.id"
                class="flex items-start justify-between gap-2 rounded-xl border p-3 transition"
                :class="selectedScrapboardId === scrapboard.id ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-slate-50 hover:border-slate-300'"
            >
                <button type="button" class="min-w-0 flex-1 text-left" @click="$emit('select', scrapboard)">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ scrapboard.name }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Updated {{ scrapboard.updated_at ? new Date(scrapboard.updated_at).toLocaleString() : 'just now' }}
                    </p>
                </button>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-white"
                        @click.stop="$emit('rename', scrapboard)"
                    >
                        Rename
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50"
                        @click.stop="$emit('delete', scrapboard)"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <p v-if="!scrapboards.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                No scrapboards yet. Create one to start planning in a spreadsheet layout.
            </p>
        </div>
    </section>
</template>
