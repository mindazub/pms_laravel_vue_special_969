<script setup>
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: 'Attachments',
  },
  hint: {
    type: String,
    default: 'Paste a screenshot, drop files here, or click to browse.',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const dropzone = ref(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadError = ref('');
const attachments = ref([...props.modelValue]);

watch(
  () => props.modelValue,
  (value) => {
    attachments.value = [...value];
  },
  { deep: true }
);

const openFilePicker = () => {
  if (props.disabled) {
    return;
  }

  dropzone.value?.focus();
  fileInput.value?.click();
};

const updateValue = (attachments) => {
  attachments.value = attachments;
  emit('update:modelValue', attachments);
};

const uploadFiles = async (files) => {
  if (props.disabled || files.length === 0) {
    return;
  }

  const formData = new FormData();
  files.forEach((file) => formData.append('files[]', file));

  isUploading.value = true;
  uploadError.value = '';

  try {
    const response = await axios.post(route('temporary-attachments.store'), formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    updateValue([...attachments.value, ...(response.data.data ?? [])]);
  } catch (error) {
    uploadError.value = error.response?.data?.message ?? 'Upload failed.';
  } finally {
    isUploading.value = false;
    dropzone.value?.focus();
  }
};

const onFileInputChange = async (event) => {
  const files = Array.from(event.target.files ?? []);
  await uploadFiles(files);
  event.target.value = '';
};

const onPaste = async (event) => {
  if (props.disabled) {
    return;
  }

  const files = Array.from(event.clipboardData?.items ?? [])
    .filter((item) => item.kind === 'file')
    .map((item) => item.getAsFile())
    .filter(Boolean);

  if (files.length === 0) {
    return;
  }

  event.preventDefault();
  await uploadFiles(files);
};

const onDrop = async (event) => {
  isDragging.value = false;

  if (props.disabled) {
    return;
  }

  const files = Array.from(event.dataTransfer?.files ?? []);
  await uploadFiles(files);
};

const removeAttachment = async (attachmentId) => {
  try {
    await axios.delete(route('temporary-attachments.destroy', attachmentId));
    updateValue(attachments.value.filter((attachment) => attachment.id !== attachmentId));
  } catch {
    uploadError.value = 'Could not remove attachment.';
  }
};
</script>

<template>
  <div>
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ label }}</label>

    <div
      ref="dropzone"
      class="rounded-xl border border-dashed p-4 transition"
      :class="[
        disabled ? 'cursor-not-allowed border-gray-200 bg-gray-50 opacity-70' : 'cursor-pointer',
        isDragging ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-white hover:border-gray-400',
      ]"
      tabindex="0"
      @click="openFilePicker"
      @paste="onPaste"
      @dragenter.prevent="isDragging = true"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
    >
      <input
        ref="fileInput"
        type="file"
        multiple
        class="hidden"
        :disabled="disabled"
        @change="onFileInputChange"
      >

      <p class="text-sm font-medium text-gray-700">Upload attachments</p>
      <p class="mt-1 text-xs text-gray-500">{{ hint }}</p>
      <p class="mt-2 text-xs text-gray-400">Up to 10MB per file.</p>
      <p v-if="isUploading" class="mt-2 text-xs font-medium text-blue-600">Uploading...</p>
      <p v-if="uploadError" class="mt-2 text-xs text-red-600">{{ uploadError }}</p>
    </div>

    <div v-if="attachments.length" class="mt-3 grid gap-3 sm:grid-cols-2">
      <div
        v-for="attachment in attachments"
        :key="attachment.id"
        class="rounded-xl border border-gray-200 bg-gray-50 p-3"
      >
        <div
          v-if="attachment.is_image && attachment.url"
          class="mb-2 overflow-hidden rounded-lg border border-gray-200 bg-white"
        >
          <img :src="attachment.url" :alt="attachment.original_name" class="h-28 w-full object-cover">
        </div>
        <div
          v-else
          class="mb-2 flex h-28 items-center justify-center rounded-lg border border-gray-200 bg-white text-xs font-semibold uppercase tracking-wide text-gray-500"
        >
          File
        </div>

        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-gray-900">{{ attachment.original_name }}</p>
            <p class="text-xs text-gray-500">{{ Math.max(1, Math.round((attachment.size ?? 0) / 1024)) }} KB</p>
          </div>
          <button
            type="button"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100"
            title="Remove attachment"
            @click.stop="removeAttachment(attachment.id)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.366-.446.911-.7 1.486-.7h.514c.575 0 1.12.254 1.486.7L12.6 4H15a1 1 0 110 2h-.533l-.804 9.646A2 2 0 0111.67 17H8.33a2 2 0 01-1.993-1.354L5.533 6H5a1 1 0 110-2h2.4l.857-.901zM8 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
