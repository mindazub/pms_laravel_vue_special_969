<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  customers: { type: Array, default: () => [] },
});

const managedCustomers = ref([]);
const customerForm = ref({ id: null, name: '', description: '' });
const customerFormErrors = ref({});
const customerFormSaving = ref(false);
const searchQuery = ref('');
const sortKey = ref('name');
const sortDirection = ref('asc');

const validationErrorsFrom = (error) => error?.response?.status === 422 ? (error.response.data.errors ?? {}) : {};

watch(
  () => props.customers,
  (customers) => {
    managedCustomers.value = customers ?? [];
  },
  { immediate: true }
);

const filteredCustomers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  const rows = managedCustomers.value.filter((customer) => {
    if (!query) {
      return true;
    }

    return [customer.name, customer.description]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });

  return [...rows].sort((left, right) => {
    const leftValue = sortKey.value === 'teams_count' ? (left.teams_count ?? 0) : (left.name ?? '');
    const rightValue = sortKey.value === 'teams_count' ? (right.teams_count ?? 0) : (right.name ?? '');

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

const loadCustomers = async () => {
  const response = await window.axios.get(route('customers.index'));
  managedCustomers.value = response.data ?? [];
};

const resetCustomerForm = () => {
  customerForm.value = { id: null, name: '', description: '' };
  customerFormErrors.value = {};
};

const startEditingCustomer = (customer) => {
  customerForm.value = {
    id: customer.id,
    name: customer.name,
    description: customer.description ?? '',
  };
  customerFormErrors.value = {};
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
</script>

<template>
  <Head title="Customers" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Customers</h2>
        <p class="mt-1 text-sm text-gray-500">Dedicated customer administration page linked from the People section.</p>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto]">
            <input v-model="searchQuery" type="text" placeholder="Search customer name or description" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="setSort('name')">Sort Name</button>
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="resetCustomerForm">New Customer</button>
          </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
          <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50">
                  <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('name')">Customer</button></th>
                    <th class="px-3 py-2"><button type="button" class="font-semibold hover:text-gray-700" @click="setSort('teams_count')">Teams</button></th>
                    <th class="px-3 py-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="customer in filteredCustomers" :key="customer.id" class="border-b border-gray-100 align-top">
                    <td class="px-3 py-3">
                      <p class="font-semibold text-gray-900">{{ customer.name }}</p>
                      <p v-if="customer.description" class="mt-1 text-xs text-gray-500">{{ customer.description }}</p>
                    </td>
                    <td class="px-3 py-3 text-gray-600">{{ customer.teams_count ?? 0 }}</td>
                    <td class="px-3 py-3 text-right">
                      <div class="inline-flex items-center gap-1">
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100" title="Edit customer" @click="startEditingCustomer(customer)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a1 1 0 01-.45.263l-4 1a1 1 0 01-1.213-1.213l1-4a1 1 0 01.263-.45l8.5-8.5a2 2 0 012.828 0z" /></svg>
                        </button>
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-300 bg-red-50 text-red-700 hover:bg-red-100" title="Delete customer" @click="deleteCustomer(customer.id)">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.366-.446.911-.7 1.486-.7h.514c.575 0 1.12.254 1.486.7L12.6 4H15a1 1 0 110 2h-.533l-.804 9.646A2 2 0 0111.67 17H8.33a2 2 0 01-1.993-1.354L5.533 6H5a1 1 0 110-2h2.4l.857-.901z" clip-rule="evenodd" /></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="!filteredCustomers.length" class="mt-3 text-sm text-gray-500">No customers match the current filters.</p>
          </article>

          <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-gray-100 pb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ customerForm.id ? 'Edit Customer' : 'Create Customer' }}</h3>
                <p class="text-sm text-gray-500">Manage customer records independently from Teams and Projects.</p>
              </div>
              <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 hover:bg-gray-50" @click="resetCustomerForm">Reset</button>
            </div>

            <div class="space-y-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Customer name</label>
                <input v-model="customerForm.name" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p v-if="customerFormErrors.name" class="mt-1 text-xs text-red-600">{{ customerFormErrors.name[0] }}</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                <textarea v-model="customerForm.description" rows="4" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
              </div>
              <div class="flex justify-end">
                <button type="button" class="rounded-md border border-transparent bg-gray-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-gray-800 disabled:opacity-60" :disabled="customerFormSaving" @click="saveCustomer">
                  {{ customerFormSaving ? 'Saving...' : (customerForm.id ? 'Save Customer' : 'Create Customer') }}
                </button>
              </div>
            </div>
          </article>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>