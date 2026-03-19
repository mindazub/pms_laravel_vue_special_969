<script setup>
import { computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    filterOptions: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    importSummary: {
        type: Object,
        default: null,
    },
});

const filterForm = useForm({
    search: props.filters.search ?? '',
    owner: props.filters.owner ?? '',
    week: props.filters.week ?? '',
    category: props.filters.category ?? '',
    linked_goal: props.filters.linked_goal ?? '',
    priority: props.filters.priority ?? '',
    workload_status: props.filters.workload_status ?? '',
    moved: props.filters.moved ?? '',
    sort: props.filters.sort ?? 'source_date_added',
    direction: props.filters.direction ?? 'desc',
});

const importForm = useForm({
    file: null,
    dry_run: false,
});

watch(
    () => props.filters,
    (nextFilters) => {
        filterForm.search = nextFilters.search ?? '';
        filterForm.owner = nextFilters.owner ?? '';
        filterForm.week = nextFilters.week ?? '';
        filterForm.category = nextFilters.category ?? '';
        filterForm.linked_goal = nextFilters.linked_goal ?? '';
        filterForm.priority = nextFilters.priority ?? '';
        filterForm.workload_status = nextFilters.workload_status ?? '';
        filterForm.moved = nextFilters.moved ?? '';
        filterForm.sort = nextFilters.sort ?? 'source_date_added';
        filterForm.direction = nextFilters.direction ?? 'desc';
    }
);

const activeFilters = computed(() => ({
    search: filterForm.search || undefined,
    owner: filterForm.owner || undefined,
    week: filterForm.week || undefined,
    category: filterForm.category || undefined,
    linked_goal: filterForm.linked_goal || undefined,
    priority: filterForm.priority || undefined,
    workload_status: filterForm.workload_status || undefined,
    moved: filterForm.moved || undefined,
    sort: filterForm.sort || undefined,
    direction: filterForm.direction || undefined,
}));

const applyFilters = () => {
    router.get(route('projects.2025'), activeFilters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.reset();
    filterForm.sort = 'source_date_added';
    filterForm.direction = 'desc';
    router.get(route('projects.2025'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const setImportFile = (event) => {
    importForm.file = event.target.files[0] ?? null;
};

const submitImport = () => {
    importForm.post(route('projects.2025.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
        },
    });
};

const sortBy = (column) => {
    const direction = filterForm.sort === column && filterForm.direction === 'asc' ? 'desc' : 'asc';
    filterForm.sort = column;
    filterForm.direction = direction;
    applyFilters();
};

const statusLabel = (status) => {
    if (!status) {
        return 'Unknown';
    }

    return status.replaceAll('_', ' ');
};

const paginationLabel = (label) => {
    return label
        .replaceAll('&laquo;', '«')
        .replaceAll('&raquo;', '»')
        .replace(/<[^>]*>/g, '')
        .trim();
};

const statusClasses = (status) => {
    if (status === 'done') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'blocked') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    if (status === 'in_progress') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
};
</script>

<template>
    <Head title="Projects 2025" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Projects 2025</h2>
                <p class="text-sm text-gray-500">CSV-style workload management using the existing task model.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Import</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Load or refresh the 2025 workload dataset</h3>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-[1fr_auto_auto]" @submit.prevent="submitImport">
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700"
                                @change="setImportFile"
                            >
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                <input v-model="importForm.dry_run" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Dry run only
                            </label>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="importForm.processing || !importForm.file"
                            >
                                {{ importForm.processing ? 'Processing...' : 'Import CSV' }}
                            </button>
                        </form>
                    </div>

                    <div v-if="importSummary" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Rows</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ importSummary.total_rows }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Processed</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ importSummary.processed_rows }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Created</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ importSummary.created_count }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Updated</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ importSummary.updated_count }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Skipped</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ importSummary.skipped_rows }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Errors</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ importSummary.error_count }}</p>
                            </div>
                        </div>

                        <div v-if="importSummary.errors?.length" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                            <p class="font-semibold">Import issues</p>
                            <ul class="mt-2 space-y-1">
                                <li v-for="error in importSummary.errors.slice(0, 10)" :key="error">{{ error }}</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Tasks</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ summary.total_tasks }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Done</p>
                        <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ summary.done_tasks }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">In progress</p>
                        <p class="mt-2 text-3xl font-semibold text-amber-600">{{ summary.in_progress_tasks }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Not started</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-700">{{ summary.not_started_tasks }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Completion</p>
                        <p class="mt-2 text-3xl font-semibold text-indigo-600">{{ summary.completion_rate }}%</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-1">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Hours / moved</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ summary.total_estimated_hours }}</p>
                        <p class="mt-1 text-sm text-slate-500">Moved: {{ summary.moved_count }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Filters</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Refine the workload view</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                                @click="resetFilters"
                            >
                                Reset filters
                            </button>
                            <Link
                                :href="route('dashboard.2025', activeFilters)"
                                class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                            >
                                Open Dashboard 2025
                            </Link>
                        </div>
                    </div>

                    <form class="mt-4 grid gap-3 lg:grid-cols-4 xl:grid-cols-8" @submit.prevent="applyFilters">
                        <input v-model="filterForm.search" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 xl:col-span-2" placeholder="Search task, owner, comments, goal">
                        <select v-model="filterForm.owner" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">All owners</option>
                            <option v-for="owner in filterOptions.owners" :key="owner" :value="owner">{{ owner }}</option>
                        </select>
                        <select v-model="filterForm.week" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">All weeks</option>
                            <option v-for="week in filterOptions.weeks" :key="week" :value="week">{{ week }}</option>
                        </select>
                        <select v-model="filterForm.category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">All categories</option>
                            <option v-for="category in filterOptions.categories" :key="category" :value="category">{{ category }}</option>
                        </select>
                        <select v-model="filterForm.linked_goal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">All goals</option>
                            <option v-for="goal in filterOptions.linked_goals" :key="goal" :value="goal">{{ goal }}</option>
                        </select>
                        <select v-model="filterForm.priority" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">All priorities</option>
                            <option v-for="priority in filterOptions.priorities" :key="priority" :value="priority">{{ priority }}</option>
                        </select>
                        <select v-model="filterForm.workload_status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">All statuses</option>
                            <option v-for="status in filterOptions.statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                        </select>
                        <select v-model="filterForm.moved" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                            <option value="">Moved or not</option>
                            <option value="yes">Moved</option>
                            <option value="no">Stayed</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 xl:col-span-1">
                            Apply
                        </button>
                    </form>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Owner</th>
                                    <th class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1" @click="sortBy('work_week_number')">Week</button>
                                    </th>
                                    <th class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1" @click="sortBy('title')">Task</button>
                                    </th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Linked Goal</th>
                                    <th class="px-4 py-3">Priority</th>
                                    <th class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1" @click="sortBy('estimated_time_hours')">Est. Hours</button>
                                    </th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1" @click="sortBy('progress')">Progress</button>
                                    </th>
                                    <th class="px-4 py-3">Comments / Blockers</th>
                                    <th class="px-4 py-3">Moved</th>
                                    <th class="px-4 py-3">Replaced Task</th>
                                    <th class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1" @click="sortBy('source_date_added')">Date Added</button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="row in rows.data" :key="row.id" class="align-top">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ row.owner }}</td>
                                    <td class="px-4 py-3">{{ row.week_label }}</td>
                                    <td class="px-4 py-3">
                                        <div class="max-w-sm">
                                            <p class="font-medium text-slate-900">{{ row.task }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">{{ row.category || '—' }}</td>
                                    <td class="px-4 py-3">{{ row.linked_goal || '—' }}</td>
                                    <td class="px-4 py-3">{{ row.priority || '—' }}</td>
                                    <td class="px-4 py-3">{{ row.estimated_time_hours ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold capitalize" :class="statusClasses(row.status)">
                                            {{ statusLabel(row.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 rounded-full bg-slate-100">
                                                <div class="h-2 rounded-full bg-indigo-500" :style="{ width: `${row.progress}%` }"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-500">{{ row.progress }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs leading-5 text-slate-600">{{ row.comments || '—' }}</td>
                                    <td class="px-4 py-3">{{ row.moved_to_next_week ? 'Yes' : 'No' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ row.replaced_task || '—' }}</td>
                                    <td class="px-4 py-3">{{ row.source_date_added || '—' }}</td>
                                </tr>
                                <tr v-if="rows.data.length === 0">
                                    <td colspan="13" class="px-4 py-10 text-center text-sm text-slate-500">No 2025 workload rows match the current filters. Import a CSV or reset the filter set.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="rows.links.length > 3" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-3 text-sm">
                        <p class="text-slate-500">Showing {{ rows.from ?? 0 }}-{{ rows.to ?? 0 }} of {{ rows.total }} rows</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <template v-for="link in rows.links" :key="link.label">
                                <span v-if="!link.url" class="rounded-lg border border-slate-200 px-3 py-1 text-slate-400">{{ paginationLabel(link.label) }}</span>
                                <Link
                                    v-else
                                    class="rounded-lg border px-3 py-1 transition"
                                    :class="link.active ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    :href="link.url"
                                    preserve-scroll
                                >
                                    {{ paginationLabel(link.label) }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>