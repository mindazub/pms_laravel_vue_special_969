<script setup>
import { computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    filterOptions: {
        type: Object,
        required: true,
    },
    kpis: {
        type: Object,
        required: true,
    },
    ownerBreakdown: {
        type: Array,
        required: true,
    },
    weeklyTrend: {
        type: Array,
        required: true,
    },
    categoryBreakdown: {
        type: Array,
        required: true,
    },
    goalBreakdown: {
        type: Array,
        required: true,
    },
    blockers: {
        type: Array,
        required: true,
    },
});

const filterForm = useForm({
    owner: props.filters.owner ?? '',
    week: props.filters.week ?? '',
    category: props.filters.category ?? '',
    linked_goal: props.filters.linked_goal ?? '',
});

watch(
    () => props.filters,
    (nextFilters) => {
        filterForm.owner = nextFilters.owner ?? '';
        filterForm.week = nextFilters.week ?? '';
        filterForm.category = nextFilters.category ?? '';
        filterForm.linked_goal = nextFilters.linked_goal ?? '';
    }
);

const applyFilters = () => {
    router.get(route('dashboard.2025'), {
        owner: filterForm.owner || undefined,
        week: filterForm.week || undefined,
        category: filterForm.category || undefined,
        linked_goal: filterForm.linked_goal || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.reset();
    router.get(route('dashboard.2025'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const maxOwnerTasks = computed(() => Math.max(...props.ownerBreakdown.map((item) => item.tasks_count), 1));
const maxWeekTasks = computed(() => Math.max(...props.weeklyTrend.map((item) => item.tasks_count), 1));
const maxCategoryTasks = computed(() => Math.max(...props.categoryBreakdown.map((item) => item.tasks_count), 1));
const maxGoalTasks = computed(() => Math.max(...props.goalBreakdown.map((item) => item.tasks_count), 1));
</script>

<template>
    <Head title="Dashboard 2025" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Dashboard 2025</h2>
                <p class="text-sm text-gray-500">KPI and workload summaries built from the imported 2025 dataset.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Filter scope</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Narrow the KPI set</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50" @click="resetFilters">
                                Reset filters
                            </button>
                            <Link :href="route('projects.2025', filterForm.data())" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Open Projects 2025
                            </Link>
                        </div>
                    </div>

                    <form class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
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
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                            Apply
                        </button>
                    </form>
                </section>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Tasks</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ kpis.total_tasks }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Completion</p>
                        <p class="mt-2 text-3xl font-semibold text-indigo-600">{{ kpis.completion_rate }}%</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Hours</p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ kpis.total_estimated_hours }}</p>
                        <p class="mt-1 text-sm text-slate-500">Completed proxy: {{ kpis.completed_hours }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Moved / blocked</p>
                        <p class="mt-2 text-3xl font-semibold text-rose-600">{{ kpis.moved_count }}</p>
                        <p class="mt-1 text-sm text-slate-500">Blocked tasks: {{ kpis.blocked_tasks }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Status mix</p>
                        <p class="mt-2 text-sm font-semibold text-emerald-600">Done: {{ kpis.done_tasks }}</p>
                        <p class="text-sm font-semibold text-amber-600">In progress: {{ kpis.in_progress_tasks }}</p>
                        <p class="text-sm font-semibold text-slate-600">Not started: {{ kpis.not_started_tasks }}</p>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-400">People performance</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900">Workload by owner</h3>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div v-for="owner in ownerBreakdown" :key="owner.owner">
                                <div class="mb-1 flex items-center justify-between gap-2 text-sm text-slate-700">
                                    <span class="font-medium text-slate-900">{{ owner.owner }}</span>
                                    <span>{{ owner.tasks_count }} tasks · {{ owner.estimated_hours }} h · {{ owner.avg_progress }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-indigo-500" :style="{ width: `${(owner.tasks_count / maxOwnerTasks) * 100}%` }"></div>
                                </div>
                            </div>
                            <p v-if="ownerBreakdown.length === 0" class="text-sm text-slate-500">No owner rows for the current filters.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Weekly trend</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Throughput by week</h3>

                        <div class="mt-4 space-y-3">
                            <div v-for="week in weeklyTrend" :key="week.week_label">
                                <div class="mb-1 flex items-center justify-between gap-2 text-sm text-slate-700">
                                    <span class="font-medium text-slate-900">{{ week.week_label }}</span>
                                    <span>{{ week.tasks_count }} tasks · {{ week.done_count }} done · {{ week.estimated_hours }} h</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${(week.tasks_count / maxWeekTasks) * 100}%` }"></div>
                                </div>
                            </div>
                            <p v-if="weeklyTrend.length === 0" class="text-sm text-slate-500">No weekly data for the current filters.</p>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Category split</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Task categories</h3>

                        <div class="mt-4 space-y-3">
                            <div v-for="item in categoryBreakdown" :key="item.category">
                                <div class="mb-1 flex items-center justify-between gap-2 text-sm text-slate-700">
                                    <span class="font-medium text-slate-900">{{ item.category }}</span>
                                    <span>{{ item.tasks_count }} tasks</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-sky-500" :style="{ width: `${(item.tasks_count / maxCategoryTasks) * 100}%` }"></div>
                                </div>
                            </div>
                            <p v-if="categoryBreakdown.length === 0" class="text-sm text-slate-500">No categories for the current filters.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Goal split</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Linked goals</h3>

                        <div class="mt-4 space-y-3">
                            <div v-for="item in goalBreakdown" :key="item.linked_goal">
                                <div class="mb-1 flex items-center justify-between gap-2 text-sm text-slate-700">
                                    <span class="font-medium text-slate-900">{{ item.linked_goal }}</span>
                                    <span>{{ item.tasks_count }} tasks</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-fuchsia-500" :style="{ width: `${(item.tasks_count / maxGoalTasks) * 100}%` }"></div>
                                </div>
                            </div>
                            <p v-if="goalBreakdown.length === 0" class="text-sm text-slate-500">No linked goals for the current filters.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Blockers and churn</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Items that need extra attention</h3>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Owner</th>
                                    <th class="px-4 py-3">Week</th>
                                    <th class="px-4 py-3">Task</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Moved</th>
                                    <th class="px-4 py-3">Comments</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="item in blockers" :key="item.id">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ item.owner }}</td>
                                    <td class="px-4 py-3">{{ item.week_label }}</td>
                                    <td class="px-4 py-3">{{ item.task }}</td>
                                    <td class="px-4 py-3 capitalize">{{ item.status?.replaceAll('_', ' ') }}</td>
                                    <td class="px-4 py-3">{{ item.moved_to_next_week ? 'Yes' : 'No' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ item.comments || '—' }}</td>
                                </tr>
                                <tr v-if="blockers.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No blockers or churn indicators in the current slice.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>