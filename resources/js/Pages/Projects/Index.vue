<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  projects: {
    type: Object,
    required: true,
  },
  selectedProject: {
    type: Object,
    default: null,
  },
  notes: {
    type: Array,
    default: () => [],
  },
  statuses: {
    type: Array,
    default: () => ['todo', 'in_progress', 'done'],
  },
  progressSteps: {
    type: Array,
    default: () => [0, 25, 50, 75, 100],
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

const selectedProjectId = computed(() => props.selectedProject?.id ?? null);

const createForm = useForm({
  project_id: selectedProjectId.value,
  title: '',
  content: '',
  status: 'todo',
  progress: 0,
});

const editForm = useForm({
  id: null,
  title: '',
  content: '',
  status: 'todo',
  progress: 0,
});

const createProjectForm = useForm({
  name: '',
});

const editProjectForm = useForm({
  id: null,
  name: '',
  selected_project_id: selectedProjectId.value,
});

const isCreateModalOpen = ref(false);
const isCreateProjectModalOpen = ref(false);
const isEditProjectModalOpen = ref(false);
const draggingNoteId = ref(null);
const dragOverStatus = ref(null);

watch(
  () => selectedProjectId.value,
  (projectId) => {
    createForm.project_id = projectId;
    createForm.status = 'todo';
    createForm.progress = 0;
    createForm.reset('title', 'content');
    createForm.clearErrors();
    isCreateModalOpen.value = false;

    editProjectForm.selected_project_id = projectId;
  },
  { immediate: true }
);

const notesByStatus = (status) => props.notes.filter((note) => note.status === status);

const projectProgressClass = (project) =>
  project.is_done
    ? 'bg-emerald-500'
    : project.completion_percentage >= 75
      ? 'bg-blue-600'
      : project.completion_percentage >= 50
        ? 'bg-blue-500'
        : 'bg-gray-400';

const openCreateModal = () => {
  if (!selectedProjectId.value) {
    return;
  }

  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
  createForm.clearErrors();
  createForm.reset('title', 'content');
  createForm.status = 'todo';
  createForm.progress = 0;
};

const createNote = () => {
  if (!selectedProjectId.value) {
    return;
  }

  createForm.project_id = selectedProjectId.value;
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
  editForm.progress = note.progress;
};

const cancelEditing = () => {
  editForm.reset();
  editForm.id = null;
  editForm.status = 'todo';
  editForm.progress = 0;
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

const updateInProgressValue = (note, value) => {
  router.patch(
    route('notes.update', note.id),
    { progress: value },
    {
      preserveScroll: true,
    }
  );
};

const getNextProgressStep = (currentProgress) => {
  const steps = [...props.progressSteps].sort((a, b) => a - b);
  const currentIndex = steps.findIndex((step) => step === Number(currentProgress));

  if (currentIndex === -1) {
    return steps[0] ?? 0;
  }

  if (currentIndex === steps.length - 1) {
    return steps[currentIndex];
  }

  return steps[currentIndex + 1];
};

const advanceProgress = (note) => {
  const nextStep = getNextProgressStep(note.progress);

  if (nextStep === note.progress) {
    return;
  }

  updateInProgressValue(note, nextStep);
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

  const payload = { status };

  if (status === 'done') {
    payload.progress = 100;
  }

  if (status === 'todo') {
    payload.progress = 0;
  }

  if (status === 'in_progress' && current.progress === 100) {
    payload.progress = 75;
  }

  router.patch(route('notes.update', current.id), payload, {
    preserveScroll: true,
    onFinish: () => {
      draggingNoteId.value = null;
      dragOverStatus.value = null;
    },
  });
};

const openCreateProjectModal = () => {
  createProjectForm.clearErrors();
  createProjectForm.reset();
  isCreateProjectModalOpen.value = true;
};

const closeCreateProjectModal = () => {
  isCreateProjectModalOpen.value = false;
  createProjectForm.clearErrors();
  createProjectForm.reset();
};

const createProject = () => {
  createProjectForm.post(route('projects.store'), {
    preserveScroll: true,
    onSuccess: () => closeCreateProjectModal(),
  });
};

const openEditProjectModal = (project) => {
  editProjectForm.id = project.id;
  editProjectForm.name = project.name;
  editProjectForm.selected_project_id = selectedProjectId.value;
  editProjectForm.clearErrors();
  isEditProjectModalOpen.value = true;
};

const closeEditProjectModal = () => {
  isEditProjectModalOpen.value = false;
  editProjectForm.clearErrors();
  editProjectForm.reset();
  editProjectForm.id = null;
  editProjectForm.selected_project_id = selectedProjectId.value;
};

const updateProject = () => {
  if (!editProjectForm.id) {
    return;
  }

  editProjectForm.selected_project_id = selectedProjectId.value;
  editProjectForm.patch(route('projects.update', editProjectForm.id), {
    preserveScroll: true,
    onSuccess: () => closeEditProjectModal(),
  });
};

const deleteProject = (projectId) => {
  if (!confirm('Delete this project and all its notes?')) {
    return;
  }

  router.delete(route('projects.destroy', projectId), {
    preserveScroll: true,
    data: {
      selected_project_id: selectedProjectId.value,
    },
  });
};
</script>

<template>
  <Head title="Projects" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-900">Projects</h2>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="mb-5 border-b border-gray-100 pb-4">
            <div class="flex items-center gap-2">
              <h3 class="text-lg font-semibold text-gray-900">Project List</h3>
              <button
                type="button"
                class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-gray-300 bg-gray-100 text-gray-700 transition hover:bg-gray-200"
                title="Add project"
                @click="openCreateProjectModal"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <p class="text-sm text-gray-500">Manage projects and select one to view its board.</p>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-gray-50">
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                  <th class="px-3 py-2">Project</th>
                  <th class="px-3 py-2">Tasks</th>
                  <th class="px-3 py-2">Completion</th>
                  <th class="px-3 py-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="project in projects.data"
                  :key="project.id"
                  class="border-b border-gray-100"
                  :class="{
                    'bg-emerald-50/70': selectedProjectId === project.id && project.is_done,
                    'bg-blue-50/70': selectedProjectId === project.id && !project.is_done,
                  }"
                >
                  <td class="px-3 py-3 font-medium text-gray-900">{{ project.name }}</td>
                  <td class="px-3 py-3 text-gray-600">{{ project.done_notes_count }} / {{ project.notes_count }}</td>
                  <td class="px-3 py-3">
                    <div class="flex items-center gap-2">
                      <div class="h-2.5 w-28 rounded-full bg-gray-200">
                        <div
                          class="h-2.5 rounded-full transition-all"
                          :class="projectProgressClass(project)"
                          :style="{ width: `${project.completion_percentage}%` }"
                        ></div>
                      </div>
                      <span class="text-xs font-semibold" :class="project.is_done ? 'text-emerald-700' : 'text-gray-700'">
                        {{ project.completion_percentage }}%
                      </span>
                    </div>
                  </td>
                  <td class="px-3 py-3 text-right">
                    <div class="inline-flex items-center gap-1.5">
                      <Link
                        :href="route('projects.index', { project: project.id })"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 transition hover:bg-indigo-100"
                        title="View board"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path d="M10 3C5.5 3 2.02 6.11 1 10c1.02 3.89 4.5 7 9 7s7.98-3.11 9-7c-1.02-3.89-4.5-7-9-7zm0 11a4 4 0 110-8 4 4 0 010 8zm0-2.5A1.5 1.5 0 1010 8a1.5 1.5 0 000 3.5z" />
                        </svg>
                      </Link>
                      <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-blue-300 bg-blue-50 text-blue-700 transition hover:bg-blue-100"
                        title="Edit project"
                        @click="openEditProjectModal(project)"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a1 1 0 01-.45.263l-4 1a1 1 0 01-1.213-1.213l1-4a1 1 0 01.263-.45l8.5-8.5a2 2 0 012.828 0z" />
                        </svg>
                      </button>
                      <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-300 bg-red-50 text-red-700 transition hover:bg-red-100"
                        title="Delete project"
                        @click="deleteProject(project.id)"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M8.257 3.099c.366-.446.911-.7 1.486-.7h.514c.575 0 1.12.254 1.486.7L12.6 4H15a1 1 0 110 2h-.533l-.804 9.646A2 2 0 0111.67 17H8.33a2 2 0 01-1.993-1.354L5.533 6H5a1 1 0 110-2h2.4l.857-.901zM8 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>


        </div>

        <div v-if="selectedProject" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="mb-5 border-b border-gray-100 pb-4">
            <h3 class="text-lg font-semibold text-gray-900">{{ selectedProject.name }} Board</h3>
            <p class="text-sm" :class="selectedProject.is_done ? 'text-emerald-700' : 'text-gray-500'">
              {{ selectedProject.done_notes_count }} of {{ selectedProject.notes_count }} tasks done
              ({{ selectedProject.completion_percentage }}%)
            </p>
          </div>

          <div class="grid gap-4 lg:grid-cols-3">
            <section
              v-for="column in columns"
              :key="column.key"
              class="min-h-[360px] rounded-xl border border-gray-200 bg-gray-50 p-4"
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
                <div class="flex items-center gap-1.5">
                  <button
                    v-if="column.key === 'todo'"
                    type="button"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100"
                    title="Add item"
                    @click="openCreateModal"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                  </button>
                  <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :class="column.badgeClass">
                    {{ notesByStatus(column.key).length }}
                  </span>
                </div>
              </div>

              <div class="space-y-3">
                <article
                  v-for="note in notesByStatus(column.key)"
                  :key="note.id"
                  class="cursor-grab rounded-lg border border-gray-200 bg-white p-3 shadow-sm active:cursor-grabbing"
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
                      <div class="grid grid-cols-2 gap-2">
                        <select
                          v-model="editForm.status"
                          class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                          <option v-for="option in columns" :key="option.key" :value="option.key">
                            {{ option.title }}
                          </option>
                        </select>
                        <select
                          v-model="editForm.progress"
                          class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                          <option v-for="step in progressSteps" :key="step" :value="step">
                            {{ step }}%
                          </option>
                        </select>
                      </div>
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

                    <div v-if="note.status === 'in_progress'" class="mt-2">
                      <div class="mb-1 flex items-center justify-between text-xs font-medium text-gray-600">
                        <span>Progress</span>
                        <span>{{ note.progress }}%</span>
                      </div>
                      <button
                        type="button"
                        class="block w-full rounded-full border border-blue-200 bg-white p-1"
                        title="Click to advance progress"
                        @click="advanceProgress(note)"
                      >
                        <div class="h-2 w-full rounded-full bg-blue-100">
                          <div
                            class="h-2 rounded-full bg-blue-500 transition-all duration-200"
                            :style="{ width: `${note.progress}%` }"
                          ></div>
                        </div>
                      </button>
                      <p class="mt-1 text-[11px] text-gray-500">Click the line to move to next step.</p>
                    </div>

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
                  Drop an item here
                </p>
              </div>
            </section>
          </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
          Select a project from the list to show its Kanban items.
        </div>
      </div>
    </div>

    <div
      v-if="isCreateProjectModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      @click.self="closeCreateProjectModal"
    >
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Create Project</h3>
            <p class="text-sm text-gray-500">Add a new project for your board.</p>
          </div>
          <button
            type="button"
            class="rounded-md px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700"
            @click="closeCreateProjectModal"
          >
            Close
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="createProject">
          <div>
            <label for="project-name" class="mb-1 block text-sm font-medium text-gray-700">Project name</label>
            <input
              id="project-name"
              v-model="createProjectForm.name"
              type="text"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Website redesign"
            >
            <p v-if="createProjectForm.errors.name" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.name }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
              @click="closeCreateProjectModal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="createProjectForm.processing"
            >
              {{ createProjectForm.processing ? 'Saving...' : 'Create Project' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div
      v-if="isEditProjectModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      @click.self="closeEditProjectModal"
    >
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Edit Project</h3>
            <p class="text-sm text-gray-500">Update project name.</p>
          </div>
          <button
            type="button"
            class="rounded-md px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700"
            @click="closeEditProjectModal"
          >
            Close
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="updateProject">
          <div>
            <label for="project-edit-name" class="mb-1 block text-sm font-medium text-gray-700">Project name</label>
            <input
              id="project-edit-name"
              v-model="editProjectForm.name"
              type="text"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Website redesign"
            >
            <p v-if="editProjectForm.errors.name" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.name }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
              @click="closeEditProjectModal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="editProjectForm.processing"
            >
              {{ editProjectForm.processing ? 'Saving...' : 'Save Project' }}
            </button>
          </div>
        </form>
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
            <h3 class="text-lg font-semibold text-gray-900">Add Item</h3>
            <p class="text-sm text-gray-500">Create a note under {{ selectedProject?.name }}.</p>
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
              placeholder="Describe the item"
            ></textarea>
            <p v-if="createForm.errors.content" class="mt-1 text-xs text-red-600">{{ createForm.errors.content }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
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
            <div>
              <label for="modal-progress" class="mb-1 block text-sm font-medium text-gray-700">Progress</label>
              <select
                id="modal-progress"
                v-model="createForm.progress"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              >
                <option v-for="step in progressSteps" :key="step" :value="step">
                  {{ step }}%
                </option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
              @click="closeCreateModal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="createForm.processing"
            >
              {{ createForm.processing ? 'Saving...' : 'Create Item' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
