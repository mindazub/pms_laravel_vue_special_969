<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

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

const page = usePage();
const selectedProjectId = computed(() => props.selectedProject?.id ?? null);
const managedTeams = ref([]);
const managedCustomers = ref([]);
const managedUsers = ref([]);
const teamOptions = computed(() => managedTeams.value ?? []);
const customerOptions = computed(() => managedCustomers.value ?? []);
const userOptions = computed(() => props.users ?? []);
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

const mentionTypes = [
  { label: 'User', value: 'User', queryValue: 'user' },
  { label: 'Team', value: 'Team', queryValue: 'team' },
  { label: 'Customer', value: 'Customer', queryValue: 'customer' },
];

const projectMentionType = ref('User');
const projectMentionQuery = ref('');
const projectMentionSuggestions = ref([]);
const noteMentionType = ref('User');
const noteMentionQuery = ref('');
const noteMentionSuggestions = ref([]);
const editNoteMentionType = ref('User');
const editNoteMentionQuery = ref('');
const editNoteMentionSuggestions = ref([]);

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

const addMention = (form, mention) => {
  const candidate = {
    id: mention.id ?? null,
    type: mention.type,
    name: mention.name,
  };

  const exists = (form.mentions ?? []).some((entry) =>
    entry.type === candidate.type && entry.name === candidate.name && Number(entry.id ?? 0) === Number(candidate.id ?? 0)
  );

  if (exists) {
    return;
  }

  form.mentions = [...(form.mentions ?? []), candidate];
};

const removeMention = (form, index) => {
  form.mentions = (form.mentions ?? []).filter((_, entryIndex) => entryIndex !== index);
};

const fetchMentionSuggestions = async (typeValue, queryText, target) => {
  if (!queryText.trim()) {
    target.value = [];
    return;
  }

  const selectedType = mentionTypes.find((item) => item.value === typeValue);

  if (!selectedType) {
    target.value = [];
    return;
  }

  try {
    const response = await fetch(route('mentions.suggestions', {
      type: selectedType.queryValue,
      q: queryText.trim(),
    }));

    if (!response.ok) {
      target.value = [];
      return;
    }

    target.value = await response.json();
  } catch {
    target.value = [];
  }
};

const validationErrorsFrom = (error) => error?.response?.status === 422 ? (error.response.data.errors ?? {}) : {};

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

const normalizeDate = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

const startOfWeek = (date) => {
  const normalized = normalizeDate(date);
  const dayIndex = (normalized.getDay() + 6) % 7;
  normalized.setDate(normalized.getDate() - dayIndex);
  return normalized;
};

const addDays = (date, days) => {
  const copy = new Date(date);
  copy.setDate(copy.getDate() + days);
  return copy;
};

const today = normalizeDate(new Date());
const currentWeekStart = startOfWeek(today);
const displayedWeekStart = ref(new Date(currentWeekStart));

const isCurrentWeekShown = computed(() => displayedWeekStart.value.getTime() === currentWeekStart.getTime());

const weekDays = computed(() =>
  Array.from({ length: 7 }, (_, index) => {
    const date = addDays(displayedWeekStart.value, index);
    const dayOfWeek = date.getDay();

    return {
      iso: date.toISOString().slice(0, 10),
      weekday: new Intl.DateTimeFormat('en-US', { weekday: 'short' }).format(date),
      month: new Intl.DateTimeFormat('en-US', { month: 'short' }).format(date),
      dayNumber: date.getDate(),
      isToday: date.getTime() === today.getTime(),
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
    };
  })
);

const weekNumber = computed(() => {
  // ISO week number using local time to avoid timezone offset shifting the date
  const date = new Date(displayedWeekStart.value);
  date.setHours(0, 0, 0, 0);
  // Move to Thursday of this week (ISO week definition anchor)
  date.setDate(date.getDate() + 4 - (date.getDay() || 7));
  const yearStart = new Date(date.getFullYear(), 0, 1);
  return Math.ceil(((date - yearStart) / 86400000 + 1) / 7);
});

const weekRangeLabel = computed(() => {
  const weekStart = displayedWeekStart.value;
  const weekEnd = addDays(weekStart, 6);
  const startFormatted = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(weekStart);
  const endFormatted = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(weekEnd);
  return `${startFormatted} - ${endFormatted}`;
});

const goToPreviousWeek = () => {
  displayedWeekStart.value = addDays(displayedWeekStart.value, -7);
};

const goToNextWeek = () => {
  displayedWeekStart.value = addDays(displayedWeekStart.value, 7);
};

const goToCurrentWeek = () => {
  displayedWeekStart.value = new Date(currentWeekStart);
};

const createForm = useForm({
  project_id: selectedProjectId.value,
  title: '',
  content: '',
  clipboard_text: '',
  attachments: [],
  status: 'todo',
  progress: 0,
  assignee_ids: [],
  mentions: [],
});

const editForm = useForm({
  id: null,
  title: '',
  content: '',
  clipboard_text: '',
  attachments: [],
  status: 'todo',
  progress: 0,
  assignee_ids: [],
  mentions: [],
});

const createProjectForm = useForm({
  name: '',
  description: '',
  clipboard_text: '',
  attachments: [],
  team_id: null,
  customer_id: null,
  project_manager_id: null,
  mentions: [],
});

const editProjectForm = useForm({
  id: null,
  name: '',
  description: '',
  clipboard_text: '',
  attachments: [],
  team_id: null,
  customer_id: null,
  project_manager_id: null,
  mentions: [],
  selected_project_id: selectedProjectId.value,
});

const pasteFromClipboard = async (form, field) => {
  if (!navigator?.clipboard?.readText) {
    alert('Clipboard paste is not available in this browser.');
    return;
  }

  try {
    const text = await navigator.clipboard.readText();
    if (!text) {
      alert('Clipboard is empty.');
      return;
    }

    form[field] = text;
  } catch {
    alert('Unable to read clipboard. Browser permission may be blocked.');
  }
};

const copyToClipboard = async (text) => {
  if (!text) {
    return;
  }

  if (!navigator?.clipboard?.writeText) {
    alert('Clipboard copy is not available in this browser.');
    return;
  }

  try {
    await navigator.clipboard.writeText(text);
  } catch {
    alert('Unable to copy text to clipboard.');
  }
};

const isCreateModalOpen = ref(false);
const isCreateProjectModalOpen = ref(false);
const isEditProjectModalOpen = ref(false);
const draggingNoteId = ref(null);
const dragOverStatus = ref(null);
const isCreateTaskDropActive = ref(false);
const createTaskFileInput = ref(null);
const isCreateProjectDropActive = ref(false);
const createProjectFileInput = ref(null);
const isEditTaskDropActive = ref(false);
const editTaskFileInput = ref(null);
const previewImageUrl = ref(null);
const previewImageName = ref('');
const expandedBoardColumn = ref(null);

const formatFileSize = (bytes) => {
  if (!bytes || Number.isNaN(bytes)) {
    return '0 B';
  }

  const units = ['B', 'KB', 'MB', 'GB'];
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  const size = bytes / (1024 ** exponent);

  return `${size.toFixed(size >= 10 || exponent === 0 ? 0 : 1)} ${units[exponent]}`;
};

watch(
  () => selectedProjectId.value,
  (projectId) => {
    expandedBoardColumn.value = null;
    createForm.project_id = projectId;
    createForm.status = 'todo';
    createForm.progress = 0;
    createForm.assignee_ids = [];
    createForm.mentions = [];
    createForm.reset('title', 'content', 'clipboard_text', 'attachments');
    createForm.clearErrors();
    isCreateModalOpen.value = false;

    editProjectForm.selected_project_id = projectId;
  },
  { immediate: true }
);

const notesByStatus = (status) => props.notes.filter((note) => note.status === status);

const isInProgressExpanded = computed(() => expandedBoardColumn.value === 'in_progress');

const isColumnPreview = (status) => isInProgressExpanded.value && status !== 'in_progress';

const boardColumnsClass = computed(() =>
  isInProgressExpanded.value
    ? 'grid gap-4 lg:grid-cols-[0.5fr_9fr_0.5fr]'
    : 'grid gap-4 lg:grid-cols-3'
);

const boardColumnClasses = (status) => [
  'min-h-[360px] rounded-xl border border-gray-200 bg-gray-50 p-4 transition-all duration-300',
  isColumnPreview(status) ? 'hidden lg:flex lg:flex-col lg:overflow-hidden lg:px-2' : '',
  dragOverStatus.value === status ? 'ring-2 ring-blue-400 ring-offset-2' : '',
];

const toggleInProgressExpansion = () => {
  expandedBoardColumn.value = isInProgressExpanded.value ? null : 'in_progress';
};

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

  isCreateTaskDropActive.value = false;
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  cleanupCreateTaskAttachmentPreviews();
  isCreateTaskDropActive.value = false;
  isCreateModalOpen.value = false;
  createForm.clearErrors();
  createForm.reset('title', 'content', 'clipboard_text', 'attachments');
  createForm.status = 'todo';
  createForm.progress = 0;
  createForm.assignee_ids = [];
  createForm.mentions = [];
  noteMentionQuery.value = '';
  noteMentionSuggestions.value = [];
};

const createNote = () => {
  if (!selectedProjectId.value) {
    return;
  }

  createForm.project_id = selectedProjectId.value;
  createForm.post(route('notes.store'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => closeCreateModal(),
  });
};

const startEditing = (note) => {
  cleanupEditTaskAttachmentPreviews();
  editForm.id = note.id;
  editForm.title = note.title;
  editForm.content = note.content;
  editForm.clipboard_text = note.clipboard_text ?? '';
  editForm.attachments = [];
  editForm.status = note.status;
  editForm.progress = note.progress;
  editForm.assignee_ids = Array.isArray(note.assignee_ids) ? [...note.assignee_ids] : [];
  editForm.mentions = Array.isArray(note.mentions) ? [...note.mentions] : [];
  editNoteMentionQuery.value = '';
  editNoteMentionSuggestions.value = [];
  isEditTaskDropActive.value = false;
};

const cancelEditing = () => {
  cleanupEditTaskAttachmentPreviews();
  editForm.reset();
  editForm.id = null;
  editForm.status = 'todo';
  editForm.progress = 0;
  editForm.assignee_ids = [];
  editForm.mentions = [];
};

const updateNote = () => {
  if (!editForm.id) {
    return;
  }

  editForm.transform((data) => ({
    ...data,
    _method: 'put',
  })).post(route('notes.update', editForm.id), {
    preserveScroll: true,
    forceFormData: true,
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
  cleanupCreateProjectAttachmentPreviews();
  isCreateProjectDropActive.value = false;
  createProjectForm.clearErrors();
  createProjectForm.reset();
  createProjectForm.team_id = null;
  createProjectForm.customer_id = null;
  createProjectForm.project_manager_id = null;
  createProjectForm.mentions = [];
  projectMentionQuery.value = '';
  projectMentionSuggestions.value = [];
  isCreateProjectModalOpen.value = true;
};

const closeCreateProjectModal = () => {
  cleanupCreateProjectAttachmentPreviews();
  isCreateProjectDropActive.value = false;
  isCreateProjectModalOpen.value = false;
  createProjectForm.clearErrors();
  createProjectForm.reset();
  createProjectForm.team_id = null;
  createProjectForm.customer_id = null;
  createProjectForm.project_manager_id = null;
  createProjectForm.mentions = [];
  projectMentionQuery.value = '';
  projectMentionSuggestions.value = [];
};

const createProject = () => {
  createProjectForm.post(route('projects.store'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => closeCreateProjectModal(),
  });
};

const openEditProjectModal = (project) => {
  editProjectForm.id = project.id;
  editProjectForm.name = project.name;
  editProjectForm.description = project.description ?? '';
  editProjectForm.clipboard_text = project.clipboard_text ?? '';
  editProjectForm.attachments = [];
  editProjectForm.team_id = project.team_id ?? null;
  editProjectForm.customer_id = project.customer_id ?? null;
  editProjectForm.project_manager_id = project.project_manager_id ?? null;
  editProjectForm.mentions = Array.isArray(project.mentions) ? [...project.mentions] : [];
  editProjectForm.selected_project_id = selectedProjectId.value;
  editProjectForm.clearErrors();
  projectMentionQuery.value = '';
  projectMentionSuggestions.value = [];
  isEditProjectModalOpen.value = true;
};

const closeEditProjectModal = () => {
  isEditProjectModalOpen.value = false;
  editProjectForm.clearErrors();
  editProjectForm.reset();
  editProjectForm.id = null;
  editProjectForm.team_id = null;
  editProjectForm.customer_id = null;
  editProjectForm.project_manager_id = null;
  editProjectForm.mentions = [];
  editProjectForm.selected_project_id = selectedProjectId.value;
};

const updateProject = () => {
  if (!editProjectForm.id) {
    return;
  }

  editProjectForm.selected_project_id = selectedProjectId.value;
  editProjectForm.patch(route('projects.update', editProjectForm.id), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => closeEditProjectModal(),
  });
};

const isImageFile = (file) => file?.type?.startsWith('image/');

const withCreateTaskPreview = (file) => {
  if (!isImageFile(file)) {
    return file;
  }

  if (!file.__previewUrl) {
    file.__previewUrl = URL.createObjectURL(file);
  }

  return file;
};

const withEditTaskPreview = (file) => {
  if (!isImageFile(file)) {
    return file;
  }

  if (!file.__previewUrl) {
    file.__previewUrl = URL.createObjectURL(file);
  }

  return file;
};

const withCreateProjectPreview = (file) => {
  if (!isImageFile(file)) {
    return file;
  }

  if (!file.__previewUrl) {
    file.__previewUrl = URL.createObjectURL(file);
  }

  return file;
};

const appendCreateTaskAttachments = (files) => {
  if (!files?.length) {
    return;
  }

  createForm.attachments = [
    ...createForm.attachments,
    ...files.map(withCreateTaskPreview),
  ];
};

const cleanupCreateTaskAttachmentPreviews = () => {
  createForm.attachments.forEach((file) => {
    if (file?.__previewUrl) {
      URL.revokeObjectURL(file.__previewUrl);
      delete file.__previewUrl;
    }
  });
};

const appendCreateProjectAttachments = (files) => {
  if (!files?.length) {
    return;
  }

  createProjectForm.attachments = [
    ...createProjectForm.attachments,
    ...files.map(withCreateProjectPreview),
  ];
};

const cleanupCreateProjectAttachmentPreviews = () => {
  createProjectForm.attachments.forEach((file) => {
    if (file?.__previewUrl) {
      URL.revokeObjectURL(file.__previewUrl);
      delete file.__previewUrl;
    }
  });
};

const appendEditTaskAttachments = (files) => {
  if (!files?.length) {
    return;
  }

  editForm.attachments = [
    ...editForm.attachments,
    ...files.map(withEditTaskPreview),
  ];
};

const cleanupEditTaskAttachmentPreviews = () => {
  editForm.attachments.forEach((file) => {
    if (file?.__previewUrl) {
      URL.revokeObjectURL(file.__previewUrl);
      delete file.__previewUrl;
    }
  });
};

const onCreateTaskAttachmentsSelected = (event) => {
  const files = Array.from(event.target.files ?? []);
  if (files.length === 0) {
    return;
  }

  appendCreateTaskAttachments(files);
  event.target.value = '';
};

const openCreateTaskFilePicker = () => {
  createTaskFileInput.value?.click();
};

const openEditTaskFilePicker = () => {
  editTaskFileInput.value?.click();
};

const openAttachmentPreview = (file) => {
  if (!file?.__previewUrl) {
    return;
  }

  previewImageUrl.value = file.__previewUrl;
  previewImageName.value = file.name ?? 'Image preview';
};

const closeAttachmentPreview = () => {
  previewImageUrl.value = null;
  previewImageName.value = '';
};

const onCreateTaskDrop = (event) => {
  event.preventDefault();
  isCreateTaskDropActive.value = false;

  const files = Array.from(event.dataTransfer?.files ?? []);
  if (files.length === 0) {
    return;
  }

  appendCreateTaskAttachments(files);
};

const onCreateTaskPaste = (event) => {
  const items = Array.from(event.clipboardData?.items ?? []);

  const pastedFiles = items
    .filter((item) => item.kind === 'file')
    .map((item) => item.getAsFile())
    .filter(Boolean);

  if (pastedFiles.length > 0) {
    event.preventDefault();
    appendCreateTaskAttachments(pastedFiles);
    return;
  }

  const pastedText = event.clipboardData?.getData('text/plain') ?? '';
  if (pastedText) {
    event.preventDefault();
    createForm.clipboard_text = createForm.clipboard_text
      ? `${createForm.clipboard_text}\n${pastedText}`
      : pastedText;
  }
};

const removeCreateTaskAttachment = (index) => {
  const file = createForm.attachments[index];
  if (file?.__previewUrl) {
    URL.revokeObjectURL(file.__previewUrl);
  }

  createForm.attachments = createForm.attachments.filter((_, i) => i !== index);
};

const onEditTaskAttachmentsSelected = (event) => {
  const files = Array.from(event.target.files ?? []);
  if (files.length === 0) {
    return;
  }

  appendEditTaskAttachments(files);
  event.target.value = '';
};

const onEditTaskDrop = (event) => {
  event.preventDefault();
  isEditTaskDropActive.value = false;

  const files = Array.from(event.dataTransfer?.files ?? []);
  if (files.length === 0) {
    return;
  }

  appendEditTaskAttachments(files);
};

const onEditTaskPaste = (event) => {
  const items = Array.from(event.clipboardData?.items ?? []);

  const pastedFiles = items
    .filter((item) => item.kind === 'file')
    .map((item) => item.getAsFile())
    .filter(Boolean);

  if (pastedFiles.length > 0) {
    event.preventDefault();
    appendEditTaskAttachments(pastedFiles);
    return;
  }

  const pastedText = event.clipboardData?.getData('text/plain') ?? '';
  if (pastedText) {
    event.preventDefault();
    editForm.clipboard_text = editForm.clipboard_text
      ? `${editForm.clipboard_text}\n${pastedText}`
      : pastedText;
  }
};

const removeEditTaskAttachment = (index) => {
  const file = editForm.attachments[index];
  if (file?.__previewUrl) {
    URL.revokeObjectURL(file.__previewUrl);
  }

  editForm.attachments = editForm.attachments.filter((_, i) => i !== index);
};

const onCreateProjectAttachmentsSelected = (event) => {
  const files = Array.from(event.target.files ?? []);
  if (files.length === 0) {
    return;
  }

  appendCreateProjectAttachments(files);
  event.target.value = '';
};

const openCreateProjectFilePicker = () => {
  createProjectFileInput.value?.click();
};

const onCreateProjectDrop = (event) => {
  event.preventDefault();
  isCreateProjectDropActive.value = false;

  const files = Array.from(event.dataTransfer?.files ?? []);
  if (files.length === 0) {
    return;
  }

  appendCreateProjectAttachments(files);
};

const onCreateProjectPaste = (event) => {
  const items = Array.from(event.clipboardData?.items ?? []);

  const pastedFiles = items
    .filter((item) => item.kind === 'file')
    .map((item) => item.getAsFile())
    .filter(Boolean);

  if (pastedFiles.length > 0) {
    event.preventDefault();
    appendCreateProjectAttachments(pastedFiles);
    return;
  }

  const pastedText = event.clipboardData?.getData('text/plain') ?? '';
  if (pastedText) {
    event.preventDefault();
    createProjectForm.clipboard_text = createProjectForm.clipboard_text
      ? `${createProjectForm.clipboard_text}\n${pastedText}`
      : pastedText;
  }
};

const removeCreateProjectAttachment = (index) => {
  const file = createProjectForm.attachments[index];
  if (file?.__previewUrl) {
    URL.revokeObjectURL(file.__previewUrl);
  }

  createProjectForm.attachments = createProjectForm.attachments.filter((_, i) => i !== index);
};

const onEditProjectAttachmentsSelected = (event) => {
  editProjectForm.attachments = Array.from(event.target.files ?? []);
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
          <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-4">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50"
              title="Previous week"
              @click="goToPreviousWeek"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L8.414 10l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
              </svg>
            </button>

            <div class="text-center">
              <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Week {{ weekNumber }} &mdash; Week View</p>
              <p class="text-base font-semibold text-gray-900">{{ weekRangeLabel }}</p>
            </div>

            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50"
              title="Next week"
              @click="goToNextWeek"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 11-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>

          <div class="mt-4 overflow-x-auto">
            <div class="grid min-w-[640px] grid-cols-7 gap-2">
              <div
                v-for="day in weekDays"
                :key="day.iso"
                class="rounded-lg border px-3 py-2 text-center"
                :class="day.isToday && isCurrentWeekShown
                  ? 'border-blue-500 bg-blue-50 text-blue-900'
                  : day.isWeekend
                    ? 'border-red-200 bg-red-50 text-red-800'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
              >
                <p class="text-[11px] font-semibold uppercase tracking-wide">{{ day.weekday }}</p>
                <p class="mt-1 text-lg font-semibold leading-none">{{ day.dayNumber }}</p>
                <p class="mt-1 text-[11px] uppercase tracking-wide">{{ day.month }}</p>
              </div>
            </div>
          </div>

          <div class="mt-3 flex justify-end" v-if="!isCurrentWeekShown">
            <button
              type="button"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 transition hover:bg-gray-50"
              @click="goToCurrentWeek"
            >
              Back to current week
            </button>
          </div>
        </div>

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
                  <td class="px-3 py-3">
                    <p class="font-medium text-gray-900">{{ project.name }}</p>
                    <p v-if="project.description" class="text-xs text-gray-500">{{ project.description }}</p>
                    <p v-if="project.clipboard_text" class="mt-1 line-clamp-2 text-xs text-indigo-700">Clipboard: {{ project.clipboard_text }}</p>
                  </td>
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

        <div class="mb-6 grid gap-6 xl:grid-cols-[1.3fr_1fr]">
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

        <section v-if="currentUserIsPrivileged" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
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

        <div v-if="selectedProject" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="mb-5 border-b border-gray-100 pb-4">
            <h3 class="text-lg font-semibold text-gray-900">{{ selectedProject.name }} Board</h3>
            <p class="text-sm" :class="selectedProject.is_done ? 'text-emerald-700' : 'text-gray-500'">
              {{ selectedProject.done_notes_count }} of {{ selectedProject.notes_count }} tasks done
              ({{ selectedProject.completion_percentage }}%)
            </p>
            <p v-if="selectedProject.description" class="mt-1 text-sm text-gray-600">{{ selectedProject.description }}</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">
              <span v-if="selectedProject.team_name" class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1">Team: {{ selectedProject.team_name }}</span>
              <span v-if="selectedProject.customer_name" class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1">Customer: {{ selectedProject.customer_name }}</span>
              <span v-if="selectedProject.project_manager_name" class="rounded-full border border-gray-300 bg-gray-50 px-2 py-1">Manager: {{ selectedProject.project_manager_name }}</span>
            </div>
            <div v-if="selectedProject.mentions?.length" class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="(mention, mentionIndex) in selectedProject.mentions"
                :key="`selected-project-mention-${mention.type}-${mention.id ?? mention.name}-${mentionIndex}`"
                class="rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-800"
              >
                @{{ mention.type }}/{{ mention.name }}
              </span>
            </div>
            <div v-if="selectedProject.clipboard_text" class="mt-2 rounded-md border border-indigo-200 bg-indigo-50 p-2">
              <div class="mb-1 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Clipboard Screen Notes</p>
                <button
                  type="button"
                  class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-100"
                  @click="copyToClipboard(selectedProject.clipboard_text)"
                >
                  Copy
                </button>
              </div>
              <p class="whitespace-pre-wrap text-xs text-indigo-900">{{ selectedProject.clipboard_text }}</p>
            </div>
            <div v-if="selectedProject.attachments?.length" class="mt-2 flex flex-wrap gap-2">
              <a
                v-for="attachment in selectedProject.attachments"
                :key="`project-attachment-${attachment.path}`"
                :href="attachment.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 transition hover:bg-gray-50"
              >
                {{ attachment.original_name }}
              </a>
            </div>
          </div>

          <div :class="boardColumnsClass">
            <section
              v-for="column in columns"
              :key="column.key"
              :class="boardColumnClasses(column.key)"
              @dragover.prevent="onDragOverColumn(column.key)"
              @dragleave="dragOverStatus = null"
              @drop="moveDraggedNoteTo(column.key)"
            >
              <template v-if="isColumnPreview(column.key)">
                <div class="h-full items-center justify-between lg:flex lg:flex-col lg:gap-4">
                  <button
                    v-if="column.key === 'todo'"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center self-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100"
                    title="Add item"
                    @click="openCreateModal"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                  </button>

                  <div class="flex flex-1 items-center justify-center overflow-hidden">
                    <p class="text-center text-[11px] font-semibold uppercase tracking-[0.3em] text-gray-400 [writing-mode:vertical-rl] rotate-180">
                      {{ column.title }}
                    </p>
                  </div>

                  <span class="self-center rounded-full border px-2 py-0.5 text-xs font-medium" :class="column.badgeClass">
                    {{ notesByStatus(column.key).length }}
                  </span>
                </div>
              </template>

              <template v-else>
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
                    <button
                      v-if="column.key === 'in_progress'"
                      type="button"
                      class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100"
                      :title="isInProgressExpanded ? 'Minimize In Progress column' : 'Maximize In Progress column'"
                      :aria-label="isInProgressExpanded ? 'Minimize In Progress column' : 'Maximize In Progress column'"
                      @click="toggleInProgressExpansion"
                    >
                      <svg v-if="!isInProgressExpanded" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3H3v5m0-5 6 6m3-6h5v5m0-5-6 6m6 3v5h-5m5 0-6-6M8 17H3v-5m0 5 6-6" />
                      </svg>
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 8H3V3m0 0 5 5m4 0 5-5m0 0v5h-5m0 4h5v5m0 0-5-5m-4 0-5 5m0 0v-5h5" />
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
                        <div>
                          <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="block text-xs font-medium text-gray-700">Clipboard / Files (optional)</label>
                            <button
                              type="button"
                              class="inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-100"
                              @click="pasteFromClipboard(editForm, 'clipboard_text')"
                            >
                              Paste text
                            </button>
                          </div>

                          <div
                            class="rounded-lg border border-dashed p-3 transition"
                            :class="isEditTaskDropActive ? 'border-indigo-400 bg-indigo-50/60' : 'border-gray-300 bg-gray-50/40'"
                            role="button"
                            tabindex="0"
                            @dragover.prevent="isEditTaskDropActive = true"
                            @dragleave="isEditTaskDropActive = false"
                            @drop="onEditTaskDrop"
                            @paste="onEditTaskPaste"
                            @click="openEditTaskFilePicker"
                          >
                            <p class="mb-2 text-[11px] text-gray-500">Paste text/screenshots, drag & drop files, or click this area to upload from PC.</p>

                            <input
                              ref="editTaskFileInput"
                              type="file"
                              multiple
                              class="hidden"
                              @change="onEditTaskAttachmentsSelected"
                            >

                            <textarea
                              v-model="editForm.clipboard_text"
                              rows="2"
                              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Paste copied screen text"
                              @click.stop
                            ></textarea>

                            <div class="mt-2">
                              <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100 hover:text-gray-800"
                                title="Attach files"
                                aria-label="Attach files"
                                @click.stop="openEditTaskFilePicker"
                              >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M8 3a5 5 0 015 5v5a3 3 0 11-6 0V8a1 1 0 112 0v5a1 1 0 102 0V8a3 3 0 10-6 0v5a5 5 0 1010 0V8a1 1 0 112 0v5a7 7 0 11-14 0V8a5 5 0 015-5z" clip-rule="evenodd" />
                                </svg>
                              </button>
                            </div>

                            <div v-if="editForm.attachments.length" class="mt-3 rounded-md border border-gray-200 bg-white p-2" @click.stop>
                              <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Attached files</p>
                              <ul class="max-h-36 space-y-1 overflow-auto">
                                <li
                                  v-for="(file, index) in editForm.attachments"
                                  :key="`${file.name}-${index}`"
                                  class="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-700"
                                >
                                  <div class="flex min-w-0 items-center gap-2">
                                    <button
                                      v-if="file.__previewUrl"
                                      type="button"
                                      class="h-8 w-8 shrink-0 overflow-hidden rounded border border-gray-200 bg-gray-100"
                                      :title="`Preview ${file.name}`"
                                      @click.stop="openAttachmentPreview(file)"
                                    >
                                      <img
                                        :src="file.__previewUrl"
                                        alt="Preview"
                                        class="h-full w-full object-cover"
                                      >
                                    </button>
                                    <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-100 text-[10px] font-semibold text-gray-500">
                                      FILE
                                    </div>
                                    <div class="min-w-0">
                                      <p class="truncate font-medium" :title="file.name">{{ index + 1 }}. {{ file.name }}</p>
                                      <p class="text-[10px] text-gray-500">{{ formatFileSize(file.size) }}</p>
                                    </div>
                                  </div>
                                  <button
                                    type="button"
                                    class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                                    @click.stop="removeEditTaskAttachment(index)"
                                  >
                                    x
                                  </button>
                                </li>
                              </ul>
                            </div>
                          </div>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                          <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Assignees</label>
                            <select
                              v-model="editForm.assignee_ids"
                              multiple
                              class="block min-h-[108px] w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                              <option v-for="user in userOptions" :key="`edit-note-assignee-${user.id}`" :value="user.id">
                                {{ user.name }}
                              </option>
                            </select>
                            <p v-if="editForm.errors.assignee_ids" class="mt-1 text-xs text-red-600">{{ editForm.errors.assignee_ids }}</p>
                          </div>
                          <div class="rounded-md border border-gray-200 bg-gray-50 p-2">
                            <p class="text-xs font-medium text-gray-700">Mentions</p>
                            <div class="mt-2 flex gap-2">
                              <select v-model="editNoteMentionType" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option v-for="item in mentionTypes" :key="`edit-note-mention-type-${item.value}`" :value="item.value">{{ item.label }}</option>
                              </select>
                              <input v-model="editNoteMentionQuery" type="text" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name to mention">
                              <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-100" @click="fetchMentionSuggestions(editNoteMentionType, editNoteMentionQuery, editNoteMentionSuggestions)">Find</button>
                            </div>
                            <div v-if="editNoteMentionSuggestions.length" class="mt-2 flex flex-wrap gap-2">
                              <button
                                v-for="item in editNoteMentionSuggestions"
                                :key="`edit-note-mention-${item.type}-${item.id ?? item.name}`"
                                type="button"
                                class="rounded-full border border-blue-300 bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100"
                                @click="addMention(editForm, item)"
                              >
                                @{{ item.type }}/{{ item.name }}
                              </button>
                            </div>
                            <div v-if="editForm.mentions?.length" class="mt-2 flex flex-wrap gap-2">
                              <span v-for="(mention, mentionIndex) in editForm.mentions" :key="`edit-note-selected-${mention.type}-${mention.id ?? mention.name}-${mentionIndex}`" class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700">
                                @{{ mention.type }}/{{ mention.name }}
                                <button type="button" class="text-gray-500 hover:text-red-600" @click="removeMention(editForm, mentionIndex)">x</button>
                              </span>
                            </div>
                            <p v-if="editForm.errors.mentions" class="mt-1 text-xs text-red-600">{{ editForm.errors.mentions }}</p>
                          </div>
                        </div>
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
                      </div>
                      <p v-if="note.content" class="mt-1 text-sm text-gray-700">{{ note.content }}</p>
                      <div v-if="note.clipboard_text" class="mt-2 rounded-md border border-indigo-200 bg-indigo-50 p-2">
                        <div class="mb-1 flex items-center justify-between gap-2">
                          <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700">Clipboard</p>
                          <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-100"
                            @click="copyToClipboard(note.clipboard_text)"
                          >
                            Copy
                          </button>
                        </div>
                        <p class="whitespace-pre-wrap text-xs text-indigo-900">{{ note.clipboard_text }}</p>
                      </div>

                      <div v-if="note.attachments?.length" class="mt-2 flex flex-wrap gap-2">
                        <a
                          v-for="attachment in note.attachments"
                          :key="`note-attachment-${note.id}-${attachment.path}`"
                          :href="attachment.url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 transition hover:bg-gray-50"
                        >
                          {{ attachment.original_name }}
                        </a>
                      </div>

                      <div v-if="note.assignee_names?.length" class="mt-2 flex flex-wrap gap-2">
                        <span
                          v-for="assignee in note.assignee_names"
                          :key="`note-assignee-${note.id}-${assignee}`"
                          class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-800"
                        >
                          {{ assignee }}
                        </span>
                      </div>

                      <div v-if="note.mentions?.length" class="mt-2 flex flex-wrap gap-2">
                        <span
                          v-for="(mention, mentionIndex) in note.mentions"
                          :key="`note-mention-${note.id}-${mention.type}-${mention.id ?? mention.name}-${mentionIndex}`"
                          class="rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-800"
                        >
                          @{{ mention.type }}/{{ mention.name }}
                        </span>
                      </div>

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
              </template>
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

          <div>
            <label for="project-description" class="mb-1 block text-sm font-medium text-gray-700">Description (optional)</label>
            <textarea
              id="project-description"
              v-model="createProjectForm.description"
              rows="3"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Describe project scope"
            ></textarea>
            <p v-if="createProjectForm.errors.description" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.description }}</p>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Team (optional)</label>
              <select v-model="createProjectForm.team_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option :value="null">No team</option>
                <option v-for="team in teamOptions" :key="`create-team-${team.id}`" :value="team.id">{{ team.name }}</option>
              </select>
              <p v-if="createProjectForm.errors.team_id" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.team_id }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Customer (optional)</label>
              <select v-model="createProjectForm.customer_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option :value="null">No customer</option>
                <option v-for="customer in customerOptions" :key="`create-customer-${customer.id}`" :value="customer.id">{{ customer.name }}</option>
              </select>
              <p v-if="createProjectForm.errors.customer_id" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.customer_id }}</p>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Project Manager (optional)</label>
            <select v-model="createProjectForm.project_manager_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option :value="null">Auto assign to creator</option>
              <option v-for="user in userOptions" :key="`create-manager-${user.id}`" :value="user.id">{{ user.name }}</option>
            </select>
            <p v-if="createProjectForm.errors.project_manager_id" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.project_manager_id }}</p>
          </div>

          <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
            <p class="text-sm font-medium text-gray-700">Mentions</p>
            <p class="mt-0.5 text-xs text-gray-500">Add mentions in `@Type/Name` format via suggestions.</p>
            <div class="mt-2 flex flex-wrap items-end gap-2">
              <select v-model="projectMentionType" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option v-for="item in mentionTypes" :key="`create-project-mention-type-${item.value}`" :value="item.value">{{ item.label }}</option>
              </select>
              <input v-model="projectMentionQuery" type="text" class="min-w-[180px] flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name to mention">
              <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100" @click="fetchMentionSuggestions(projectMentionType, projectMentionQuery, projectMentionSuggestions)">Find</button>
            </div>
            <div v-if="projectMentionSuggestions.length" class="mt-2 flex flex-wrap gap-2">
              <button
                v-for="item in projectMentionSuggestions"
                :key="`create-project-mention-${item.type}-${item.id ?? item.name}`"
                type="button"
                class="rounded-full border border-blue-300 bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100"
                @click="addMention(createProjectForm, item)"
              >
                @{{ item.type }}/{{ item.name }}
              </button>
            </div>
            <div v-if="createProjectForm.mentions?.length" class="mt-2 flex flex-wrap gap-2">
              <span v-for="(mention, mentionIndex) in createProjectForm.mentions" :key="`create-project-selected-${mention.type}-${mention.id ?? mention.name}-${mentionIndex}`" class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700">
                @{{ mention.type }}/{{ mention.name }}
                <button type="button" class="text-gray-500 hover:text-red-600" @click="removeMention(createProjectForm, mentionIndex)">x</button>
              </span>
            </div>
            <p v-if="createProjectForm.errors.mentions" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.mentions }}</p>
          </div>

          <div>
            <div class="mb-1 flex items-center justify-between gap-2">
              <label for="project-clipboard" class="block text-sm font-medium text-gray-700">Clipboard / Files (optional)</label>
              <button
                type="button"
                class="inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                @click="pasteFromClipboard(createProjectForm, 'clipboard_text')"
              >
                Paste text
              </button>
            </div>

            <div
              class="rounded-lg border border-dashed p-3 transition"
              :class="isCreateProjectDropActive ? 'border-indigo-400 bg-indigo-50/60' : 'border-gray-300 bg-gray-50/40'"
              role="button"
              tabindex="0"
              @dragover.prevent="isCreateProjectDropActive = true"
              @dragleave="isCreateProjectDropActive = false"
              @drop="onCreateProjectDrop"
              @paste="onCreateProjectPaste"
              @click="openCreateProjectFilePicker"
            >
              <p class="mb-2 text-xs text-gray-500">Paste text/screenshots, drag & drop files, or click this area to upload from PC.</p>

              <input
                ref="createProjectFileInput"
                id="project-attachments"
                type="file"
                multiple
                class="hidden"
                @change="onCreateProjectAttachmentsSelected"
              >

              <textarea
                id="project-clipboard"
                v-model="createProjectForm.clipboard_text"
                rows="3"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Paste copied screen notes for this project"
                @click.stop
              ></textarea>

              <div class="mt-2">
                <button
                  type="button"
                  class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100 hover:text-gray-800"
                  title="Attach files"
                  aria-label="Attach files"
                  @click.stop="openCreateProjectFilePicker"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8 3a5 5 0 015 5v5a3 3 0 11-6 0V8a1 1 0 112 0v5a1 1 0 102 0V8a3 3 0 10-6 0v5a5 5 0 1010 0V8a1 1 0 112 0v5a7 7 0 11-14 0V8a5 5 0 015-5z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>

              <div v-if="createProjectForm.attachments.length" class="mt-3 rounded-md border border-gray-200 bg-white p-2" @click.stop>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Attached files</p>
                <ul class="max-h-36 space-y-1 overflow-auto">
                  <li
                    v-for="(file, index) in createProjectForm.attachments"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-700"
                  >
                    <div class="flex min-w-0 items-center gap-2">
                      <button
                        v-if="file.__previewUrl"
                        type="button"
                        class="h-8 w-8 shrink-0 overflow-hidden rounded border border-gray-200 bg-gray-100"
                        :title="`Preview ${file.name}`"
                        @click.stop="openAttachmentPreview(file)"
                      >
                        <img
                          :src="file.__previewUrl"
                          alt="Preview"
                          class="h-full w-full object-cover"
                        >
                      </button>
                      <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-100 text-[10px] font-semibold text-gray-500">
                        FILE
                      </div>
                      <div class="min-w-0">
                        <p class="truncate font-medium" :title="file.name">{{ index + 1 }}. {{ file.name }}</p>
                        <p class="text-[10px] text-gray-500">{{ formatFileSize(file.size) }}</p>
                      </div>
                    </div>
                    <button
                      type="button"
                      class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                      @click.stop="removeCreateProjectAttachment(index)"
                    >
                      x
                    </button>
                  </li>
                </ul>
              </div>
            </div>

            <p v-if="createProjectForm.errors.clipboard_text" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.clipboard_text }}</p>
            <p v-if="createProjectForm.errors.attachments" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors.attachments }}</p>
            <p v-if="createProjectForm.errors['attachments.0']" class="mt-1 text-xs text-red-600">{{ createProjectForm.errors['attachments.0'] }}</p>
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

          <div>
            <label for="project-edit-description" class="mb-1 block text-sm font-medium text-gray-700">Description (optional)</label>
            <textarea
              id="project-edit-description"
              v-model="editProjectForm.description"
              rows="3"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Describe project scope"
            ></textarea>
            <p v-if="editProjectForm.errors.description" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.description }}</p>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Team (optional)</label>
              <select v-model="editProjectForm.team_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option :value="null">No team</option>
                <option v-for="team in teamOptions" :key="`edit-team-${team.id}`" :value="team.id">{{ team.name }}</option>
              </select>
              <p v-if="editProjectForm.errors.team_id" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.team_id }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Customer (optional)</label>
              <select v-model="editProjectForm.customer_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option :value="null">No customer</option>
                <option v-for="customer in customerOptions" :key="`edit-customer-${customer.id}`" :value="customer.id">{{ customer.name }}</option>
              </select>
              <p v-if="editProjectForm.errors.customer_id" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.customer_id }}</p>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Project Manager (optional)</label>
            <select v-model="editProjectForm.project_manager_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option :value="null">Keep current manager</option>
              <option v-for="user in userOptions" :key="`edit-manager-${user.id}`" :value="user.id">{{ user.name }}</option>
            </select>
            <p v-if="editProjectForm.errors.project_manager_id" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.project_manager_id }}</p>
          </div>

          <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
            <p class="text-sm font-medium text-gray-700">Mentions</p>
            <div class="mt-2 flex flex-wrap items-end gap-2">
              <select v-model="projectMentionType" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option v-for="item in mentionTypes" :key="`edit-project-mention-type-${item.value}`" :value="item.value">{{ item.label }}</option>
              </select>
              <input v-model="projectMentionQuery" type="text" class="min-w-[180px] flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name to mention">
              <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100" @click="fetchMentionSuggestions(projectMentionType, projectMentionQuery, projectMentionSuggestions)">Find</button>
            </div>
            <div v-if="projectMentionSuggestions.length" class="mt-2 flex flex-wrap gap-2">
              <button
                v-for="item in projectMentionSuggestions"
                :key="`edit-project-mention-${item.type}-${item.id ?? item.name}`"
                type="button"
                class="rounded-full border border-blue-300 bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100"
                @click="addMention(editProjectForm, item)"
              >
                @{{ item.type }}/{{ item.name }}
              </button>
            </div>
            <div v-if="editProjectForm.mentions?.length" class="mt-2 flex flex-wrap gap-2">
              <span v-for="(mention, mentionIndex) in editProjectForm.mentions" :key="`edit-project-selected-${mention.type}-${mention.id ?? mention.name}-${mentionIndex}`" class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700">
                @{{ mention.type }}/{{ mention.name }}
                <button type="button" class="text-gray-500 hover:text-red-600" @click="removeMention(editProjectForm, mentionIndex)">x</button>
              </span>
            </div>
            <p v-if="editProjectForm.errors.mentions" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.mentions }}</p>
          </div>

          <div>
            <div class="mb-1 flex items-center justify-between gap-2">
              <label for="project-edit-clipboard" class="block text-sm font-medium text-gray-700">Clipboard Screen Notes (optional)</label>
              <button
                type="button"
                class="inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                @click="pasteFromClipboard(editProjectForm, 'clipboard_text')"
              >
                Paste from clipboard
              </button>
            </div>
            <textarea
              id="project-edit-clipboard"
              v-model="editProjectForm.clipboard_text"
              rows="3"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Paste copied screen notes for this project"
            ></textarea>
            <p v-if="editProjectForm.errors.clipboard_text" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.clipboard_text }}</p>
          </div>

          <div>
            <label for="project-edit-attachments" class="mb-1 block text-sm font-medium text-gray-700">Add attachments (optional)</label>
            <input
              id="project-edit-attachments"
              type="file"
              multiple
              class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700"
              @change="onEditProjectAttachmentsSelected"
            >
            <p class="mt-1 text-xs text-gray-500">New files are appended to existing project attachments.</p>
            <p v-if="editProjectForm.errors.attachments" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors.attachments }}</p>
            <p v-if="editProjectForm.errors['attachments.0']" class="mt-1 text-xs text-red-600">{{ editProjectForm.errors['attachments.0'] }}</p>
          </div>

          <div v-if="selectedProject?.attachments?.length" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Existing files</p>
            <div class="flex flex-wrap gap-2">
              <a
                v-for="attachment in selectedProject.attachments"
                :key="`project-edit-attachment-${attachment.path}`"
                :href="attachment.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 transition hover:bg-gray-50"
              >
                {{ attachment.original_name }}
              </a>
            </div>
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
            <label for="modal-content" class="mb-1 block text-sm font-medium text-gray-700">Description (optional)</label>
            <textarea
              id="modal-content"
              v-model="createForm.content"
              rows="4"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="Describe the item"
            ></textarea>
            <p v-if="createForm.errors.content" class="mt-1 text-xs text-red-600">{{ createForm.errors.content }}</p>
          </div>

          <div>
            <div class="mb-1 flex items-center justify-between gap-2">
              <label for="modal-clipboard" class="block text-sm font-medium text-gray-700">Clipboard / Files (optional)</label>
              <button
                type="button"
                class="inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                @click="pasteFromClipboard(createForm, 'clipboard_text')"
              >
                Paste text
              </button>
            </div>

            <div
              class="rounded-lg border border-dashed p-3 transition"
              :class="isCreateTaskDropActive ? 'border-indigo-400 bg-indigo-50/60' : 'border-gray-300 bg-gray-50/40'"
              role="button"
              tabindex="0"
              @dragover.prevent="isCreateTaskDropActive = true"
              @dragleave="isCreateTaskDropActive = false"
              @drop="onCreateTaskDrop"
              @paste="onCreateTaskPaste"
              @click="openCreateTaskFilePicker"
            >
              <p class="mb-2 text-xs text-gray-500">Paste text/screenshots, drag & drop files, or click this area to upload from PC.</p>

              <input
                ref="createTaskFileInput"
                id="task-attachments"
                type="file"
                multiple
                class="hidden"
                @change="onCreateTaskAttachmentsSelected"
              >

              <textarea
                id="modal-clipboard"
                v-model="createForm.clipboard_text"
                rows="3"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Paste copied text here"
                @click.stop
              ></textarea>

              <div class="mt-2">
                <button
                  type="button"
                  class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100 hover:text-gray-800"
                  title="Attach files"
                  aria-label="Attach files"
                  @click.stop="openCreateTaskFilePicker"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8 3a5 5 0 015 5v5a3 3 0 11-6 0V8a1 1 0 112 0v5a1 1 0 102 0V8a3 3 0 10-6 0v5a5 5 0 1010 0V8a1 1 0 112 0v5a7 7 0 11-14 0V8a5 5 0 015-5z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>

              <div v-if="createForm.attachments.length" class="mt-3 rounded-md border border-gray-200 bg-white p-2" @click.stop>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Attached files</p>
                <ul class="max-h-36 space-y-1 overflow-auto">
                  <li
                  v-for="(file, index) in createForm.attachments"
                  :key="`${file.name}-${index}`"
                  class="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-700"
                >
                  <div class="flex min-w-0 items-center gap-2">
                    <button
                      v-if="file.__previewUrl"
                      type="button"
                      class="h-8 w-8 shrink-0 overflow-hidden rounded border border-gray-200 bg-gray-100"
                      :title="`Preview ${file.name}`"
                      @click.stop="openAttachmentPreview(file)"
                    >
                      <img
                        :src="file.__previewUrl"
                        alt="Preview"
                        class="h-full w-full object-cover"
                      >
                    </button>
                    <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-100 text-[10px] font-semibold text-gray-500">
                      FILE
                    </div>
                    <div class="min-w-0">
                      <p class="truncate font-medium" :title="file.name">{{ index + 1 }}. {{ file.name }}</p>
                      <p class="text-[10px] text-gray-500">{{ formatFileSize(file.size) }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                    @click.stop="removeCreateTaskAttachment(index)"
                  >
                    x
                  </button>
                  </li>
                </ul>
              </div>
            </div>

            <p v-if="createForm.errors.clipboard_text" class="mt-1 text-xs text-red-600">{{ createForm.errors.clipboard_text }}</p>
            <p v-if="createForm.errors.attachments" class="mt-1 text-xs text-red-600">{{ createForm.errors.attachments }}</p>
            <p v-if="createForm.errors['attachments.0']" class="mt-1 text-xs text-red-600">{{ createForm.errors['attachments.0'] }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2 grid gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Assignees</label>
                <select
                  v-model="createForm.assignee_ids"
                  multiple
                  class="block min-h-[120px] w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                  <option v-for="user in userOptions" :key="`create-note-assignee-${user.id}`" :value="user.id">
                    {{ user.name }}
                  </option>
                </select>
                <p v-if="createForm.errors.assignee_ids" class="mt-1 text-xs text-red-600">{{ createForm.errors.assignee_ids }}</p>
              </div>
              <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                <p class="text-sm font-medium text-gray-700">Mentions</p>
                <div class="mt-2 flex gap-2">
                  <select v-model="noteMentionType" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option v-for="item in mentionTypes" :key="`create-note-mention-type-${item.value}`" :value="item.value">{{ item.label }}</option>
                  </select>
                  <input v-model="noteMentionQuery" type="text" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name to mention">
                  <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100" @click="fetchMentionSuggestions(noteMentionType, noteMentionQuery, noteMentionSuggestions)">Find</button>
                </div>
                <div v-if="noteMentionSuggestions.length" class="mt-2 flex flex-wrap gap-2">
                  <button
                    v-for="item in noteMentionSuggestions"
                    :key="`create-note-mention-${item.type}-${item.id ?? item.name}`"
                    type="button"
                    class="rounded-full border border-blue-300 bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100"
                    @click="addMention(createForm, item)"
                  >
                    @{{ item.type }}/{{ item.name }}
                  </button>
                </div>
                <div v-if="createForm.mentions?.length" class="mt-2 flex flex-wrap gap-2">
                  <span v-for="(mention, mentionIndex) in createForm.mentions" :key="`create-note-selected-${mention.type}-${mention.id ?? mention.name}-${mentionIndex}`" class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700">
                    @{{ mention.type }}/{{ mention.name }}
                    <button type="button" class="text-gray-500 hover:text-red-600" @click="removeMention(createForm, mentionIndex)">x</button>
                  </span>
                </div>
                <p v-if="createForm.errors.mentions" class="mt-1 text-xs text-red-600">{{ createForm.errors.mentions }}</p>
              </div>
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

    <div
      v-if="previewImageUrl"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 px-4"
      @click="closeAttachmentPreview"
    >
      <div class="max-h-[90vh] max-w-4xl" @click.stop>
        <div class="mb-2 flex items-center justify-between gap-4 rounded-md bg-white/95 px-3 py-2 text-xs text-slate-700">
          <p class="truncate font-medium" :title="previewImageName">{{ previewImageName }}</p>
          <button
            type="button"
            class="rounded border border-slate-300 px-2 py-1 font-semibold text-slate-700 transition hover:bg-slate-100"
            @click="closeAttachmentPreview"
          >
            Close
          </button>
        </div>
        <img :src="previewImageUrl" :alt="previewImageName" class="max-h-[82vh] max-w-full rounded-md border border-white/20 bg-white object-contain" >
      </div>
    </div>
  </AuthenticatedLayout>
</template>
