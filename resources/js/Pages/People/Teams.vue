<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  teams: { type: Array, default: () => [] },
  customers: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
});

const managedTeams = ref([]);
const searchQuery = ref('');
const customerFilter = ref('');
const managerFilter = ref('');
const sortKey = ref('name');
const sortDirection = ref('asc');

const teamForm = ref({ id: null, name: '', description: '', customer_id: null, manager_id: null, member_ids: [] });
const teamFormErrors = ref({});
const teamFormSaving = ref(false);
const selectedTeamId = ref(null);
const teamMembersForm = ref([]);
const teamMembersErrors = ref({});
const teamMembersSaving = ref(false);
const teamRoleOptions = ['Manager', 'HR', 'User'];

const validationErrorsFrom = (error) => error?.response?.status === 422 ? (error.response.data.errors ?? {}) : {};
const normalizeTeamPayload = (team) => ({ ...team, users: team.users ?? [], users_count: team.users_count ?? team.users?.length ?? 0 });

watch(
  () => props.teams,
  (teams) => {
    managedTeams.value = (teams ?? []).map(normalizeTeamPayload);
  },
  { immediate: true }
);

const userOptions = computed(() => props.users ?? []);
const customerOptions = computed(() => props.customers ?? []);
const managerOptions = computed(() => userOptions.value);

const selectedTeam = computed(() => managedTeams.value.find((team) => team.id === selectedTeamId.value) ?? null);

const filteredTeams = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  const rows = managedTeams.value.filter((team) => {
    const matchesQuery = !query || [team.name, team.description, team.customer?.name, team.manager?.name]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));

    const matchesCustomer = !customerFilter.value || String(team.customer_id ?? team.customer?.id ?? '') === customerFilter.value;
    const matchesManager = !managerFilter.value || String(team.manager_id ?? team.manager?.id ?? '') === managerFilter.value;

    return matchesQuery && matchesCustomer && matchesManager;
  });

  const sorted = [...rows].sort((left, right) => {
    const leftValue = sortKey.value === 'customer'
      ? (left.customer?.name ?? '')
      : sortKey.value === 'manager'
        ? (left.manager?.name ?? '')
        : sortKey.value === 'members'
          ? (left.users_count ?? 0)
          : (left.name ?? '');
    const rightValue = sortKey.value === 'customer'
      ? (right.customer?.name ?? '')
      : sortKey.value === 'manager'
        ? (right.manager?.name ?? '')
        : sortKey.value === 'members'
          ? (right.users_count ?? 0)
          : (right.name ?? '');

    if (leftValue === rightValue) {
      return 0;
    }

    const comparison = leftValue > rightValue ? 1 : -1;
    return sortDirection.value === 'asc' ? comparison : -comparison;
  });

  return sorted;
});

const setSort = (key) => {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortKey.value = key;
  sortDirection.value = 'asc';
};

const loadTeams = async () => {
  const response = await window.axios.get(route('teams.index'));
  managedTeams.value = (response.data ?? []).map(normalizeTeamPayload);

  if (selectedTeamId.value !== null) {
    const activeTeam = managedTeams.value.find((team) => team.id === selectedTeamId.value);

    if (activeTeam) {
      teamMembersForm.value = (activeTeam.users ?? []).map((user) => ({
        id: user.id,
        role: user.pivot?.role ?? (user.id === activeTeam.manager_id ? 'Manager' : 'User'),
      }));
    }
  }
};

const resetTeamForm = () => {
  teamForm.value = { id: null, name: '', description: '', customer_id: null, manager_id: null, member_ids: [] };
  teamFormErrors.value = {};
};

const startEditingTeam = (team) => {
  teamForm.value = {
    id: team.id,
    name: team.name,
    description: team.description ?? '',
    customer_id: team.customer_id ?? team.customer?.id ?? null,
    manager_id: team.manager_id ?? team.manager?.id ?? null,
    member_ids: (team.users ?? []).map((user) => user.id).filter((userId) => userId !== (team.manager_id ?? team.manager?.id ?? null)),
  };
  teamFormErrors.value = {};
};

const saveTeam = async () => {
  teamFormSaving.value = true;
  teamFormErrors.value = {};

  try {
    const payload = {
      name: teamForm.value.name,
      description: teamForm.value.description,
      customer_id: teamForm.value.customer_id,
      manager_id: teamForm.value.manager_id,
      member_ids: teamForm.value.member_ids,
    };

    if (teamForm.value.id) {
      await window.axios.put(route('teams.update', teamForm.value.id), payload);
    } else {
      await window.axios.post(route('teams.store'), payload);
    }

    await loadTeams();
    resetTeamForm();
  } catch (error) {
    teamFormErrors.value = validationErrorsFrom(error);
  } finally {
    teamFormSaving.value = false;
  }
};

const deleteTeam = async (teamId) => {
  if (!confirm('Delete this team?')) {
    return;
  }

  try {
    await window.axios.delete(route('teams.destroy', teamId));
    await loadTeams();

    if (selectedTeamId.value === teamId) {
      selectedTeamId.value = null;
      teamMembersForm.value = [];
      teamMembersErrors.value = {};
    }
  } catch {
    alert('Unable to delete this team.');
  }
};

const openTeamMembersEditor = (team) => {
  selectedTeamId.value = team.id;
  teamMembersErrors.value = {};
  teamMembersForm.value = (team.users ?? []).map((user) => ({
    id: user.id,
    role: user.pivot?.role ?? (user.id === team.manager_id ? 'Manager' : 'User'),
  }));
};

const addTeamMemberRow = () => {
  teamMembersForm.value = [...teamMembersForm.value, { id: null, role: 'User' }];
};

const removeTeamMemberRow = (index) => {
  const activeTeam = selectedTeam.value;
  const member = teamMembersForm.value[index];

  if (activeTeam && member?.id === activeTeam.manager_id) {
    return;
  }

  teamMembersForm.value = teamMembersForm.value.filter((_, memberIndex) => memberIndex !== index);
};

const saveTeamMembers = async () => {
  if (!selectedTeam.value) {
    return;
  }

  teamMembersSaving.value = true;
  teamMembersErrors.value = {};

  try {
    const members = teamMembersForm.value
      .filter((member) => member.id)
      .reduce((carry, member) => {
        if (!carry.some((existing) => existing.id === member.id)) {
          carry.push({ id: member.id, role: member.role });
        }

        return carry;
      }, []);

    await window.axios.patch(route('teams.members.sync', selectedTeam.value.id), { members });
    await loadTeams();
  } catch (error) {
    teamMembersErrors.value = validationErrorsFrom(error);
  } finally {
    teamMembersSaving.value = false;
  }
};
</script>

<template>
  <Head title="Teams" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Teams</h2>
        <p class="mt-1 text-sm text-gray-500">Full-width team management with filtering, member maintenance, and compact action controls.</p>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_repeat(2,minmax(180px,0.4fr))_auto]">
            <input v-model="searchQuery" type="text" placeholder="Search team, description, manager, customer" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <select v-model="customerFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">All customers</option>
              <option v-for="customer in customerOptions" :key="`team-filter-customer-${customer.id}`" :value="String(customer.id)">{{ customer.name }}</option>
            </select>
            <select v-model="managerFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">All managers</option>
              <option v-for="manager in managerOptions" :key="`team-filter-manager-${manager.id}`" :value="String(manager.id)">{{ manager.name }}</option>
            </select>
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="resetTeamForm">New Team</button>
          </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
          <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50">
                  <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('name')">Team</button></th>
                    <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('customer')">Customer</button></th>
                    <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('manager')">Manager</button></th>
                    <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('members')">Members</button></th>
                    <th class="px-3 py-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="team in filteredTeams" :key="team.id" class="border-b border-gray-100 align-top">
                    <td class="px-3 py-3">
                      <p class="font-semibold text-gray-900">{{ team.name }}</p>
                      <p v-if="team.description" class="mt-1 text-xs text-gray-500">{{ team.description }}</p>
                    </td>
                    <td class="px-3 py-3 text-gray-600">{{ team.customer?.name ?? 'No customer' }}</td>
                    <td class="px-3 py-3 text-gray-600">{{ team.manager?.name ?? 'Unassigned' }}</td>
                    <td class="px-3 py-3 text-gray-600">{{ team.users_count ?? team.users?.length ?? 0 }}</td>
                    <td class="px-3 py-3 text-right">
                      <div class="inline-flex items-center gap-1">
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100" title="Edit team" @click="startEditingTeam(team)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a1 1 0 01-.45.263l-4 1a1 1 0 01-1.213-1.213l1-4a1 1 0 01.263-.45l8.5-8.5a2 2 0 012.828 0z" /></svg>
                        </button>
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100" title="Manage members" @click="openTeamMembersEditor(team)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 7a3 3 0 11-6 0 3 3 0 016 0zm-8 8a5 5 0 1110 0H5zm11-5h4v2h-4v4h-2v-4h-4v-2h4V6h2v4z" /></svg>
                        </button>
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-300 bg-red-50 text-red-700 hover:bg-red-100" title="Delete team" @click="deleteTeam(team.id)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.366-.446.911-.7 1.486-.7h.514c.575 0 1.12.254 1.486.7L12.6 4H15a1 1 0 110 2h-.533l-.804 9.646A2 2 0 0111.67 17H8.33a2 2 0 01-1.993-1.354L5.533 6H5a1 1 0 110-2h2.4l.857-.901z" clip-rule="evenodd" /></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="!filteredTeams.length" class="mt-3 text-sm text-gray-500">No teams match the current filters.</p>
          </article>

          <div class="space-y-6">
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">{{ teamForm.id ? 'Edit Team' : 'Create Team' }}</h3>
                  <p class="text-sm text-gray-500">Keep form actions separate while the table remains the main workspace.</p>
                </div>
                <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="resetTeamForm">Reset</button>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Team name</label>
                  <input v-model="teamForm.name" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                  <p v-if="teamFormErrors.name" class="mt-1 text-xs text-red-600">{{ teamFormErrors.name[0] }}</p>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                  <textarea v-model="teamForm.description" rows="3" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Manager</label>
                  <select v-model="teamForm.manager_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option :value="null">Current user</option>
                    <option v-for="user in userOptions" :key="`team-user-${user.id}`" :value="user.id">{{ user.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Customer</label>
                  <select v-model="teamForm.customer_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option :value="null">No customer</option>
                    <option v-for="customer in customerOptions" :key="`team-customer-${customer.id}`" :value="customer.id">{{ customer.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Initial members</label>
                  <select v-model="teamForm.member_ids" multiple class="block min-h-[120px] w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option v-for="user in userOptions" :key="`team-member-${user.id}`" :value="user.id">{{ user.name }}</option>
                  </select>
                </div>
                <div class="flex justify-end">
                  <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="teamFormSaving" @click="saveTeam">
                    {{ teamFormSaving ? 'Saving...' : (teamForm.id ? 'Save Team' : 'Create Team') }}
                  </button>
                </div>
              </div>
            </article>

            <article v-if="selectedTeam" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">{{ selectedTeam.name }} Members</h3>
                  <p class="text-sm text-gray-500">Add, remove, and reassign team-scoped roles.</p>
                </div>
                <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="addTeamMemberRow">Add Member</button>
              </div>

              <div class="space-y-2">
                <div v-for="(member, memberIndex) in teamMembersForm" :key="`team-member-row-${memberIndex}`" class="grid gap-2 rounded-md border border-gray-200 bg-gray-50 p-2 md:grid-cols-[minmax(0,1fr)_140px_auto]">
                  <select v-model="member.id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option :value="null">Select user</option>
                    <option v-for="user in userOptions" :key="`team-member-user-${selectedTeam.id}-${user.id}`" :value="user.id">{{ user.name }}</option>
                  </select>
                  <select v-model="member.role" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="member.id === selectedTeam.manager_id">
                    <option v-for="role in teamRoleOptions" :key="`team-member-role-${role}`" :value="role">{{ role }}</option>
                  </select>
                  <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="member.id === selectedTeam.manager_id" @click="removeTeamMemberRow(memberIndex)">Remove</button>
                </div>
              </div>

              <p v-if="teamMembersErrors.members" class="mt-2 text-xs text-red-600">{{ teamMembersErrors.members[0] }}</p>

              <div class="mt-4 flex justify-end">
                <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="teamMembersSaving" @click="saveTeamMembers">
                  {{ teamMembersSaving ? 'Saving...' : 'Save Members' }}
                </button>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>