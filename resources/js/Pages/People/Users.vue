<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  canManageRoles: { type: Boolean, default: false },
});

const managedUsers = ref([]);
const searchQuery = ref('');
const roleFilter = ref('');
const teamFilter = ref('');
const assignmentFilter = ref('');
const sortKey = ref('name');
const sortDirection = ref('asc');
const userRoleErrors = ref({});
const savingUserRoleIds = ref([]);
const globalRoleOptions = ['Admin', 'CEO', 'Manager', 'HR', 'User'];

const normalizeUserPayload = (user) => ({
  ...user,
  selected_role: user.role_names?.[0] ?? 'User',
  team_names: user.team_names ?? [],
  team_count: user.team_count ?? 0,
});

watch(
  () => props.users,
  (users) => {
    managedUsers.value = (users ?? []).map(normalizeUserPayload);
  },
  { immediate: true }
);

const availableTeams = computed(() => [...new Set(managedUsers.value.flatMap((user) => user.team_names ?? []))].sort());

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  const rows = managedUsers.value.filter((user) => {
    const matchesQuery = !query || [user.name, user.email, ...(user.team_names ?? [])]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
    const matchesRole = !roleFilter.value || (user.role_names?.[0] ?? '') === roleFilter.value;
    const matchesTeam = !teamFilter.value || (user.team_names ?? []).includes(teamFilter.value);
    const matchesAssignment = !assignmentFilter.value
      || (assignmentFilter.value === 'with-team' && user.team_count > 0)
      || (assignmentFilter.value === 'without-team' && user.team_count === 0);

    return matchesQuery && matchesRole && matchesTeam && matchesAssignment;
  });

  return [...rows].sort((left, right) => {
    const leftValue = sortKey.value === 'role'
      ? (left.role_names?.[0] ?? '')
      : sortKey.value === 'teams'
        ? (left.team_names ?? []).join(', ')
        : sortKey.value === 'team_count'
          ? left.team_count
          : sortKey.value === 'email'
            ? left.email
            : left.name;
    const rightValue = sortKey.value === 'role'
      ? (right.role_names?.[0] ?? '')
      : sortKey.value === 'teams'
        ? (right.team_names ?? []).join(', ')
        : sortKey.value === 'team_count'
          ? right.team_count
          : sortKey.value === 'email'
            ? right.email
            : right.name;

    if (leftValue === rightValue) {
      return 0;
    }

    const comparison = leftValue > rightValue ? 1 : -1;
    return sortDirection.value === 'asc' ? comparison : -comparison;
  });
});

const setSort = (key) => {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortKey.value = key;
  sortDirection.value = 'asc';
};

const saveUserRole = async (userId) => {
  const user = managedUsers.value.find((entry) => entry.id === userId);

  if (!user || !props.canManageRoles) {
    return;
  }

  savingUserRoleIds.value = [...savingUserRoleIds.value, userId];
  userRoleErrors.value = { ...userRoleErrors.value, [userId]: null };

  try {
    const response = await window.axios.put(route('users.roles.update', userId), {
      role: user.selected_role,
    });

    managedUsers.value = managedUsers.value.map((entry) =>
      entry.id === userId ? normalizeUserPayload(response.data) : entry
    );
  } catch (error) {
    userRoleErrors.value = {
      ...userRoleErrors.value,
      [userId]: error?.response?.data?.errors?.role?.[0] ?? 'Unable to update role.',
    };
  } finally {
    savingUserRoleIds.value = savingUserRoleIds.value.filter((id) => id !== userId);
  }
};
</script>

<template>
  <Head title="Users" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Users</h2>
        <p class="mt-1 text-sm text-gray-500">Renamed from User Roles. Team membership is now visible and filterable alongside role administration.</p>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px_200px_180px]">
            <input v-model="searchQuery" type="text" placeholder="Search user, email, or team" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <select v-model="roleFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">All roles</option>
              <option v-for="role in globalRoleOptions" :key="`role-filter-${role}`" :value="role">{{ role }}</option>
            </select>
            <select v-model="teamFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">All teams</option>
              <option v-for="team in availableTeams" :key="`team-filter-${team}`" :value="team">{{ team }}</option>
            </select>
            <select v-model="assignmentFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">All assignments</option>
              <option value="with-team">With team</option>
              <option value="without-team">Without team</option>
            </select>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-gray-50">
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                  <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('name')">User</button></th>
                  <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('email')">Email</button></th>
                  <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('role')">Role</button></th>
                  <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('teams')">Teams</button></th>
                  <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('team_count')">Team Count</button></th>
                  <th v-if="canManageRoles" class="px-3 py-2 text-right">Update</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in filteredUsers" :key="user.id" class="border-b border-gray-100 align-top">
                  <td class="px-3 py-3 font-semibold text-gray-900">{{ user.name }}</td>
                  <td class="px-3 py-3 text-gray-600">{{ user.email }}</td>
                  <td class="px-3 py-3">
                    <div class="max-w-[180px]">
                      <select v-model="user.selected_role" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!canManageRoles">
                        <option v-for="role in globalRoleOptions" :key="`user-role-${user.id}-${role}`" :value="role">{{ role }}</option>
                      </select>
                      <p v-if="userRoleErrors[user.id]" class="mt-1 text-xs text-red-600">{{ userRoleErrors[user.id] }}</p>
                    </div>
                  </td>
                  <td class="px-3 py-3 text-gray-600">
                    <div v-if="user.team_names.length" class="flex flex-wrap gap-1.5">
                      <span v-for="teamName in user.team_names" :key="`${user.id}-${teamName}`" class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-700">{{ teamName }}</span>
                    </div>
                    <span v-else class="text-xs font-medium text-rose-600">No team assigned</span>
                  </td>
                  <td class="px-3 py-3 text-gray-600">{{ user.team_count }}</td>
                  <td v-if="canManageRoles" class="px-3 py-3 text-right">
                    <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="savingUserRoleIds.includes(user.id)" @click="saveUserRole(user.id)">
                      {{ savingUserRoleIds.includes(user.id) ? 'Saving...' : 'Save Role' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="!filteredUsers.length" class="mt-3 text-sm text-gray-500">No users match the current filters.</p>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>