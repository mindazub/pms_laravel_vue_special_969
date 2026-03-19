<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  notes: {
    type: Array,
    default: () => [],
  },
  statuses: {
    type: Array,
    default: () => ['todo', 'in_progress', 'done'],
  },
});

const statusMeta = {
  todo: {
    title: 'To Do',
    description: 'Ideas waiting to be started',
    badgeClass: 'bg-slate-100 text-slate-800 border-slate-300',
  },
  in_progress: {
    title: 'In Progress',
    description: 'Tasks currently being worked on',
    badgeClass: 'bg-amber-100 text-amber-900 border-amber-300',
  },
  done: {
    title: 'Done',
    description: 'Completed tasks',
    badgeClass: 'bg-emerald-100 text-emerald-900 border-emerald-300',
  },
};

const columns = computed(() =>
  props.statuses.map((status) => ({
    key: status,
    ...statusMeta[status],
  }))
);

const createForm = useForm({
  title: '',
  content: '',
  status: 'todo',
});

const isCreateModalOpen = ref(false);

const editForm = useForm({
  id: null,
  title: '',
  content: '',
  status: 'todo',
});

const draggingNoteId = ref(null);
const dragOverStatus = ref(null);

const notesByStatus = (status) => props.notes.filter((note) => note.status === status);

const openCreateModal = () => {
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
  createForm.clearErrors();
  createForm.reset('title', 'content');
  createForm.status = 'todo';
};

const createNote = () => {
  createForm.post(route('notes.store'), {
    preserveScroll: true,
    onSuccess: () => closeCreateModal(),
  });
};

const startEditing = (note) => {
  editForm.id = note.id;
  editForm.title = note.title;
  editForm.content = note.content;
  editForm.status = note.status;
};

const cancelEditing = () => {
  editForm.reset();
  editForm.id = null;
};

const updateNote = () => {
  if (!editForm.id) {
    return;
  }

  editForm.put(route('notes.update', editForm.id), {
    preserveScroll: true,
    onSuccess: () => cancelEditing(),
  });
};

const deleteNote = (noteId) => {
  if (!confirm('Delete this note?')) {
    return;
  }

  router.delete(route('notes.destroy', noteId), {
    preserveScroll: true,
    onSuccess: () => {
      if (editForm.id === noteId) {
        cancelEditing();
      }
    },
  });
};

const onDragStart = (event, noteId) => {
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(noteId));
  draggingNoteId.value = noteId;
};

const onDragEnd = () => {
  draggingNoteId.value = null;
  dragOverStatus.value = null;
};

const onDragOverColumn = (status) => {
  dragOverStatus.value = status;
};

const moveDraggedNoteTo = (status) => {
  const noteId = draggingNoteId.value;

  if (!noteId) {
    dragOverStatus.value = null;
    return;
  }

  const current = props.notes.find((note) => note.id === noteId);

  if (!current || current.status === status) {
    draggingNoteId.value = null;
    dragOverStatus.value = null;
    return;
  }

  router.patch(
    route('notes.update', current.id),
    { status },
    {
      preserveScroll: true,
      onFinish: () => {
        draggingNoteId.value = null;
        dragOverStatus.value = null;
      },
    }
  );
};
</script>

<template>
  <Head title="Notes Board" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-900">Notes Kanban Board</h2>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Your Notes</h3>
            <p class="text-sm text-gray-500">Manage tasks and move them across your board.</p>
          </div>
          <button
            type="button"
            class="inline-flex items-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-800"
            @click="openCreateModal"
          >
            Add Note
          </button>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <section
            v-for="column in columns"
            :key="column.key"
            class="min-h-[360px] rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            :class="{ 'ring-2 ring-blue-400 ring-offset-2': dragOverStatus === column.key }"
            @dragover.prevent="onDragOverColumn(column.key)"
            @dragleave="dragOverStatus = null"
            @drop="moveDraggedNoteTo(column.key)"
          >
            <div class="mb-3 flex items-start justify-between gap-2">
              <div>
                <h3 class="text-base font-semibold text-gray-900">{{ column.title }}</h3>
                <p class="text-xs text-gray-500">{{ column.description }}</p>
              </div>
              <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :class="column.badgeClass">
                {{ notesByStatus(column.key).length }}
              </span>
            </div>

            <div class="space-y-3">
              <article
                v-for="note in notesByStatus(column.key)"
                :key="note.id"
                class="cursor-grab rounded-lg border border-gray-200 bg-gray-50 p-3 active:cursor-grabbing"
                draggable="true"
                @dragstart="onDragStart($event, note.id)"
                @dragend="onDragEnd"
              >
                <template v-if="editForm.id === note.id">
                  <div class="space-y-2">
                    <input
                      v-model="editForm.title"
                      type="text"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                    <textarea
                      v-model="editForm.content"
                      rows="3"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    ></textarea>
                    <select
                      v-model="editForm.status"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                      <option v-for="option in columns" :key="option.key" :value="option.key">
                        {{ option.title }}
                      </option>
                    </select>
                    <div class="flex justify-end gap-2">
                      <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-emerald-300 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100"
                        :disabled="editForm.processing"
                        title="Save"
                        @click="updateNote"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0l-3-3a1 1 0 011.42-1.42L9 11.586l6.546-6.546a1 1 0 011.158-.21z" clip-rule="evenodd" />
                        </svg>
                      </button>
                      <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100"
                        title="Cancel"
                        @click="cancelEditing"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </template>

                <template v-else>
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="text-sm font-semibold text-gray-900">{{ note.title }}</h4>
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-500" title="Drag to move">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zM7 10a1 1 0 11-2 0 1 1 0 012 0zM7 16a1 1 0 11-2 0 1 1 0 012 0zM15 4a1 1 0 11-2 0 1 1 0 012 0zM15 10a1 1 0 11-2 0 1 1 0 012 0zM15 16a1 1 0 11-2 0 1 1 0 012 0z" />
                      </svg>
                    </span>
                  </div>
                  <p class="mt-1 text-sm text-gray-700">{{ note.content }}</p>
                  <div class="mt-3 flex justify-end gap-2">
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-blue-300 bg-blue-50 text-blue-700 transition hover:bg-blue-100"
                      title="Edit"
                      @click="startEditing(note)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a1 1 0 01-.45.263l-4 1a1 1 0 01-1.213-1.213l1-4a1 1 0 01.263-.45l8.5-8.5a2 2 0 012.828 0z" />
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-300 bg-red-50 text-red-700 transition hover:bg-red-100"
                      title="Delete"
                      @click="deleteNote(note.id)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.366-.446.911-.7 1.486-.7h.514c.575 0 1.12.254 1.486.7L12.6 4H15a1 1 0 110 2h-.533l-.804 9.646A2 2 0 0111.67 17H8.33a2 2 0 01-1.993-1.354L5.533 6H5a1 1 0 110-2h2.4l.857-.901zM8 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                      </svg>
                    </button>
                  </div>
                </template>
              </article>

              <p v-if="notesByStatus(column.key).length === 0" class="rounded-lg border border-dashed border-gray-300 p-4 text-center text-sm text-gray-400">
                Drop a note here
              </p>
            </div>
          </section>
        </div>
      </div>
    </div>

    <div
      v-if="isCreateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      @click.self="closeCreateModal"
    >
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Add New Note</h3>
            <p class="text-sm text-gray-500">Create a new card and choose where it starts.</p>
          </div>
          <button
            type="button"
            class="rounded-md px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700"
            @click="closeCreateModal"
          >
            Close
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="createNote">
          <div>
            <label for="modal-title" class="mb-1 block text-sm font-medium text-gray-700">Title</label>
            <input
              id="modal-title"
              v-model="createForm.title"
              type="text"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Write a concise title"
            >
            <p v-if="createForm.errors.title" class="mt-1 text-xs text-red-600">{{ createForm.errors.title }}</p>
          </div>

          <div>
            <label for="modal-content" class="mb-1 block text-sm font-medium text-gray-700">Content</label>
            <textarea
              id="modal-content"
              v-model="createForm.content"
              rows="4"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Describe the task or idea"
            ></textarea>
            <p v-if="createForm.errors.content" class="mt-1 text-xs text-red-600">{{ createForm.errors.content }}</p>
          </div>

          <div>
            <label for="modal-status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
            <select
              id="modal-status"
              v-model="createForm.status"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option v-for="column in columns" :key="column.key" :value="column.key">
                {{ column.title }}
              </option>
            </select>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50"
              @click="closeCreateModal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="createForm.processing"
            >
              {{ createForm.processing ? 'Saving...' : 'Create Note' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>