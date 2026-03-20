<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
  largestTeams: {
    type: Array,
    default: () => [],
  },
  roleDistribution: {
    type: Array,
    default: () => [],
  },
  usersWithoutTeams: {
    type: Array,
    default: () => [],
  },
  recentCustomers: {
    type: Array,
    default: () => [],
  },
});

const metricCards = [
  { label: 'Total Users', value: props.metrics.total_users, tone: 'text-slate-900 bg-slate-100' },
  { label: 'Teams', value: props.metrics.total_teams, tone: 'text-blue-900 bg-blue-100' },
  { label: 'Customers', value: props.metrics.total_customers, tone: 'text-emerald-900 bg-emerald-100' },
  { label: 'Managers', value: props.metrics.manager_count, tone: 'text-amber-900 bg-amber-100' },
  { label: 'HR Users', value: props.metrics.hr_count, tone: 'text-fuchsia-900 bg-fuchsia-100' },
  { label: 'Without Team', value: props.metrics.unassigned_users_count, tone: 'text-rose-900 bg-rose-100' },
];
</script>

<template>
  <Head title="People Overview" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">People Overview</h2>
          <p class="mt-1 text-sm text-gray-500">HR dashboard for headcount coverage, team structure, and organization health.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('people.teams.index')" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50">Teams</Link>
          <Link :href="route('people.customers.index')" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50">Customers</Link>
          <Link :href="route('people.users.index')" class="rounded-md border border-gray-900 bg-gray-900 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800">Users</Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="card in metricCards"
            :key="card.label"
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ card.value }}</p>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="card.tone">Live</span>
            </div>
          </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
          <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Team Coverage</h3>
                <p class="text-sm text-gray-500">Assigned users versus people currently without any team membership.</p>
              </div>
              <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">{{ metrics.coverage_rate }}% coverage</span>
            </div>

            <div class="mb-5 h-3 w-full rounded-full bg-gray-100">
              <div class="h-3 rounded-full bg-blue-600" :style="{ width: `${metrics.coverage_rate}%` }"></div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assigned</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ metrics.assigned_users_count }}</p>
                <p class="mt-1 text-sm text-gray-500">Users currently attached to at least one team.</p>
              </div>
              <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Needs Placement</p>
                <p class="mt-2 text-2xl font-semibold text-rose-900">{{ metrics.unassigned_users_count }}</p>
                <p class="mt-1 text-sm text-rose-700">Users who should be reviewed for assignment or onboarding.</p>
              </div>
            </div>
          </article>

          <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 border-b border-gray-100 pb-4">
              <h3 class="text-lg font-semibold text-gray-900">Role Distribution</h3>
              <p class="text-sm text-gray-500">Visible headcount by primary role.</p>
            </div>

            <div class="space-y-3">
              <div v-for="role in roleDistribution" :key="role.role" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-sm font-medium text-gray-700">{{ role.role }}</span>
                  <span class="text-lg font-semibold text-gray-900">{{ role.count }}</span>
                </div>
              </div>
            </div>
          </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Largest Teams</h3>
                <p class="text-sm text-gray-500">Quick look at the teams carrying the most people.</p>
              </div>
              <Link :href="route('people.teams.index')" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Open teams</Link>
            </div>

            <div class="space-y-3">
              <div v-for="team in largestTeams" :key="team.id" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h4 class="text-sm font-semibold text-gray-900">{{ team.name }}</h4>
                    <p class="mt-1 text-xs text-gray-500">Manager: {{ team.manager_name ?? 'Unassigned' }}</p>
                    <p class="text-xs text-gray-500">Customer: {{ team.customer_name ?? 'No customer' }}</p>
                  </div>
                  <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">{{ team.users_count }} people</span>
                </div>
              </div>
              <p v-if="!largestTeams.length" class="text-sm text-gray-500">No visible teams yet.</p>
            </div>
          </article>

          <div class="space-y-6">
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">Users Without Teams</h3>
                  <p class="text-sm text-gray-500">Priority list for onboarding, transfers, or assignment follow-up.</p>
                </div>
                <Link :href="route('people.users.index')" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Open users</Link>
              </div>
              <div class="space-y-3">
                <div v-for="user in usersWithoutTeams" :key="user.id" class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                  <p class="text-sm font-semibold text-rose-900">{{ user.name }}</p>
                  <p class="text-xs text-rose-700">{{ user.email }}</p>
                </div>
                <p v-if="!usersWithoutTeams.length" class="text-sm text-gray-500">Everyone visible is already assigned to a team.</p>
              </div>
            </article>

            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">Recent Customers</h3>
                  <p class="text-sm text-gray-500">Customer records currently shaping visible teams.</p>
                </div>
                <Link :href="route('people.customers.index')" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Open customers</Link>
              </div>

              <div class="space-y-3">
                <div v-for="customer in recentCustomers" :key="customer.id" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="text-sm font-semibold text-gray-900">{{ customer.name }}</p>
                      <p v-if="customer.description" class="mt-1 text-xs text-gray-500">{{ customer.description }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ customer.teams_count }} teams</span>
                  </div>
                </div>
                <p v-if="!recentCustomers.length" class="text-sm text-gray-500">No visible customers yet.</p>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
