<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
  teams: {
    type: Array,
    default: () => [],
  },
  customers: {
    type: Array,
    default: () => [],
  },
  users: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const managedTeams = ref([]);
const managedCustomers = ref([]);
const managedUsers = ref([]);
const userOptions = computed(() => props.users ?? []);
const customerOptions = computed(() => managedCustomers.value ?? []);
const teamRoleOptions = ['Manager', 'HR', 'User'];
const globalRoleOptions = ['Admin', 'CEO', 'Manager', 'HR', 'User'];
const currentUserIsPrivileged = computed(() => {
  const roleNames = page.props.auth?.user?.role_names ?? [];

  return roleNames.includes('Admin') || roleNames.includes('CEO');
});

const teamForm = ref({
  id: null,
  name: '',
  description: '',
  customer_id: null,
  manager_id: null,
  member_ids: [],
});
const teamFormErrors = ref({});
const teamFormSaving = ref(false);

const customerForm = ref({
  id: null,
  name: '',
  description: '',
});
const customerFormErrors = ref({});
const customerFormSaving = ref(false);

const selectedTeamId = ref(null);
const teamMembersForm = ref([]);
const teamMembersErrors = ref({});
const teamMembersSaving = ref(false);

const userRoleErrors = ref({});
const savingUserRoleIds = ref([]);

const selectedTeam = computed(() =>
  managedTeams.value.find((team) => team.id === selectedTeamId.value) ?? null
);

watch(
  () => props.teams,
  (teams) => {
    managedTeams.value = (teams ?? []).map((team) => ({
      ...team,
      users: team.users ?? [],
    }));
  },
  { immediate: true }
);

watch(
  () => props.customers,
  (customers) => {
    managedCustomers.value = customers ?? [];
  },
  { immediate: true }
);

const validationErrorsFrom = (error) =>
  error?.response?.status === 422 ? (error.response.data.errors ?? {}) : {};

const normalizeTeamPayload = (team) => ({
  ...team,
  users: team.users ?? [],
});

const normalizeUserPayload = (user) => ({
  ...user,
  selected_role: user.role_names?.[0] ?? 'User',
});

const resetTeamForm = () => {
  teamForm.value = {
    id: null,
    name: '',
    description: '',
    customer_id: null,
    manager_id: null,
    member_ids: [],
  };
  teamFormErrors.value = {};
};

const resetCustomerForm = () => {
  customerForm.value = {
    id: null,
    name: '',
    description: '',
  };
  customerFormErrors.value = {};
};

const startEditingTeam = (team) => {
  teamForm.value = {
    id: team.id,
    name: team.name,
    description: team.description ?? '',
    customer_id: team.customer_id ?? team.customer?.id ?? null,
    manager_id: team.manager_id ?? team.manager?.id ?? null,
    member_ids: (team.users ?? [])
      .map((user) => user.id)
      .filter((userId) => userId !== (team.manager_id ?? team.manager?.id ?? null)),
  };
  teamFormErrors.value = {};
};

const startEditingCustomer = (customer) => {
  customerForm.value = {
    id: customer.id,
    name: customer.name,
    description: customer.description ?? '',
  };
  customerFormErrors.value = {};
};

const loadTeams = async () => {
  const response = await window.axios.get(route('teams.index'));
  managedTeams.value = (response.data ?? []).map(normalizeTeamPayload);

  if (selectedTeamId.value !== null) {
    const currentTeam = managedTeams.value.find((team) => team.id === selectedTeamId.value);

    if (currentTeam) {
      teamMembersForm.value = (currentTeam.users ?? []).map((user) => ({
        id: user.id,
        role: user.pivot?.role ?? (user.id === currentTeam.manager_id ? 'Manager' : 'User'),
      }));
    }
  }
};

const loadCustomers = async () => {
  const response = await window.axios.get(route('customers.index'));
  managedCustomers.value = response.data ?? [];
};

const loadManagedUsers = async () => {
  if (!currentUserIsPrivileged.value) {
    managedUsers.value = [];
    return;
  }

  const response = await window.axios.get(route('users.roles.index'));
  managedUsers.value = (response.data ?? []).map(normalizeUserPayload);
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

const saveCustomer = async () => {
  customerFormSaving.value = true;
  customerFormErrors.value = {};

  try {
    const payload = {
      name: customerForm.value.name,
      description: customerForm.value.description,
    };

    if (customerForm.value.id) {
      await window.axios.put(route('customers.update', customerForm.value.id), payload);
    } else {
      await window.axios.post(route('customers.store'), payload);
    }

    await loadCustomers();
    resetCustomerForm();
  } catch (error) {
    customerFormErrors.value = validationErrorsFrom(error);
  } finally {
    customerFormSaving.value = false;
  }
};

const deleteCustomer = async (customerId) => {
  if (!confirm('Delete this customer?')) {
    return;
  }

  try {
    await window.axios.delete(route('customers.destroy', customerId));
    await loadCustomers();
  } catch {
    alert('Unable to delete this customer.');
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

const saveUserRole = async (userId) => {
  const user = managedUsers.value.find((entry) => entry.id === userId);

  if (!user) {
    return;
  }

  savingUserRoleIds.value = [...savingUserRoleIds.value, userId];
  userRoleErrors.value = {
    ...userRoleErrors.value,
    [userId]: null,
  };

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
      [userId]: validationErrorsFrom(error).role?.[0] ?? 'Unable to update role.',
    };
  } finally {
    savingUserRoleIds.value = savingUserRoleIds.value.filter((id) => id !== userId);
  }
};

onMounted(async () => {
  await Promise.all([loadTeams(), loadCustomers(), loadManagedUsers()]);
});
</script>

<template>
  <Head title="People" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">People</h2>
        <Link
          :href="route('projects.index')"
          class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50"
        >
          Back to Projects
        </Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div class="grid gap-6 xl:grid-cols-[1.3fr_1fr]">
          <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Teams</h3>
                <p class="text-sm text-gray-500">Create teams, assign managers, and manage team membership.</p>
              </div>
              <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50"
                @click="resetTeamForm"
              >
                New Team
              </button>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1.3fr)]">
              <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Team name</label>
                  <input v-model="teamForm.name" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                  <p v-if="teamFormErrors.name" class="mt-1 text-xs text-red-600">{{ teamFormErrors.name[0] }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                  <textarea v-model="teamForm.description" rows="3" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                  <p v-if="teamFormErrors.description" class="mt-1 text-xs text-red-600">{{ teamFormErrors.description[0] }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Manager</label>
                  <select v-model="teamForm.manager_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option :value="null">Current user</option>
                    <option v-for="user in userOptions" :key="`team-manager-${user.id}`" :value="user.id">{{ user.name }}</option>
                  </select>
                  <p v-if="teamFormErrors.manager_id" class="mt-1 text-xs text-red-600">{{ teamFormErrors.manager_id[0] }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Customer</label>
                  <select v-model="teamForm.customer_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option :value="null">No customer</option>
                    <option v-for="customer in customerOptions" :key="`team-customer-${customer.id}`" :value="customer.id">{{ customer.name }}</option>
                  </select>
                  <p v-if="teamFormErrors.customer_id" class="mt-1 text-xs text-red-600">{{ teamFormErrors.customer_id[0] }}</p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700">Initial members</label>
                  <select v-model="teamForm.member_ids" multiple class="block min-h-[120px] w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option v-for="user in userOptions" :key="`team-member-${user.id}`" :value="user.id">{{ user.name }}</option>
                  </select>
                  <p class="mt-1 text-xs text-gray-500">Selected members start with the `User` team role. The manager is always included.</p>
                  <p v-if="teamFormErrors.member_ids" class="mt-1 text-xs text-red-600">{{ teamFormErrors.member_ids[0] }}</p>
                </div>

                <div class="flex justify-end gap-2">
                  <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="resetTeamForm">Reset</button>
                  <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="teamFormSaving" @click="saveTeam">
                    {{ teamFormSaving ? 'Saving...' : (teamForm.id ? 'Save Team' : 'Create Team') }}
                  </button>
                </div>
              </div>

              <div class="space-y-3">
                <article v-for="team in managedTeams" :key="`team-card-${team.id}`" class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <h4 class="text-sm font-semibold text-gray-900">{{ team.name }}</h4>
                      <p v-if="team.description" class="mt-1 text-xs text-gray-500">{{ team.description }}</p>
                    </div>
                    <div class="flex gap-1">
                      <button type="button" class="rounded-md border border-blue-300 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-100" @click="startEditingTeam(team)">Edit</button>
                      <button type="button" class="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-100" @click="openTeamMembersEditor(team)">Members</button>
                      <button type="button" class="rounded-md border border-red-300 bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-100" @click="deleteTeam(team.id)">Delete</button>
                    </div>
                  </div>

                  <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-gray-600">
                    <span class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1">Manager: {{ team.manager?.name ?? 'Unassigned' }}</span>
                    <span class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1">Customer: {{ team.customer?.name ?? 'None' }}</span>
                    <span class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1">Members: {{ team.users?.length ?? 0 }}</span>
                  </div>
                </article>

                <div v-if="selectedTeam" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                  <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                      <h4 class="text-sm font-semibold text-gray-900">Manage {{ selectedTeam.name }} Members</h4>
                      <p class="text-xs text-gray-500">Managers can add, remove, and reassign team-scoped roles.</p>
                    </div>
                    <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50" @click="addTeamMemberRow">Add member</button>
                  </div>

                  <div class="space-y-2">
                    <div v-for="(member, memberIndex) in teamMembersForm" :key="`team-member-row-${memberIndex}`" class="grid gap-2 rounded-md border border-gray-200 bg-white p-2 md:grid-cols-[minmax(0,1fr)_140px_auto]">
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

                  <div class="mt-3 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="teamMembersSaving" @click="saveTeamMembers">
                      {{ teamMembersSaving ? 'Saving...' : 'Save Members' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Customers</h3>
                <p class="text-sm text-gray-500">Maintain customer records used by teams and projects.</p>
              </div>
              <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50"
                @click="resetCustomerForm"
              >
                New Customer
              </button>
            </div>

            <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Customer name</label>
                <input v-model="customerForm.name" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p v-if="customerFormErrors.name" class="mt-1 text-xs text-red-600">{{ customerFormErrors.name[0] }}</p>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                <textarea v-model="customerForm.description" rows="3" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                <p v-if="customerFormErrors.description" class="mt-1 text-xs text-red-600">{{ customerFormErrors.description[0] }}</p>
              </div>

              <div class="flex justify-end gap-2">
                <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="resetCustomerForm">Reset</button>
                <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="customerFormSaving" @click="saveCustomer">
                  {{ customerFormSaving ? 'Saving...' : (customerForm.id ? 'Save Customer' : 'Create Customer') }}
                </button>
              </div>
            </div>

            <div class="mt-4 space-y-3">
              <article v-for="customer in managedCustomers" :key="`customer-card-${customer.id}`" class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <h4 class="text-sm font-semibold text-gray-900">{{ customer.name }}</h4>
                    <p v-if="customer.description" class="mt-1 text-xs text-gray-500">{{ customer.description }}</p>
                  </div>
                  <div class="flex gap-1">
                    <button type="button" class="rounded-md border border-blue-300 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-100" @click="startEditingCustomer(customer)">Edit</button>
                    <button type="button" class="rounded-md border border-red-300 bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-100" @click="deleteCustomer(customer.id)">Delete</button>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </div>

        <section v-if="currentUserIsPrivileged" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="mb-4 border-b border-gray-100 pb-4">
            <h3 class="text-lg font-semibold text-gray-900">User Roles</h3>
            <p class="text-sm text-gray-500">Assign one primary role to each account. Admin and CEO remain globally scoped.</p>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-gray-50">
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                  <th class="px-3 py-2">User</th>
                  <th class="px-3 py-2">Email</th>
                  <th class="px-3 py-2">Current Role</th>
                  <th class="px-3 py-2 text-right">Update</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in managedUsers" :key="`managed-user-${user.id}`" class="border-b border-gray-100">
                  <td class="px-3 py-3 font-medium text-gray-900">{{ user.name }}</td>
                  <td class="px-3 py-3 text-gray-600">{{ user.email }}</td>
                  <td class="px-3 py-3">
                    <div class="max-w-[180px]">
                      <select v-model="user.selected_role" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option v-for="role in globalRoleOptions" :key="`user-role-${user.id}-${role}`" :value="role">{{ role }}</option>
                      </select>
                      <p v-if="userRoleErrors[user.id]" class="mt-1 text-xs text-red-600">{{ userRoleErrors[user.id] }}</p>
                    </div>
                  </td>
                  <td class="px-3 py-3 text-right">
                    <button
                      type="button"
                      class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60"
                      :disabled="savingUserRoleIds.includes(user.id)"
                      @click="saveUserRole(user.id)"
                    >
                      {{ savingUserRoleIds.includes(user.id) ? 'Saving...' : 'Save Role' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
