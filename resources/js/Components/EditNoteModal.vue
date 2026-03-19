<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
  show: Boolean,
  note: Object, // The note object to edit, or null for creation
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  title: '',
  content: '',
});

watch(() => props.note, (newNote) => {
  if (newNote) {
    form.title = newNote.title;
    form.content = newNote.content;
  } else {
    form.reset();
  }
}, { immediate: true });

const submit = () => {
  if (props.note) {
    // Update existing note
    form.put(route('notes.update', props.note.id), {
      onSuccess: () => {
        emit('success');
        emit('close');
      },
    });
  } else {
    // Create new note
    form.post(route('notes.store'), {
      onSuccess: () => {
        emit('success');
        emit('close');
      },
    });
  }
};

const closeModal = () => {
  form.reset();
  emit('close');
};
</script>

<template>
  <Modal :show="show" @close="closeModal">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900">
        {{ note ? 'Edit Note' : 'Create New Note' }}
      </h2>

      <div class="mt-6">
        <InputLabel for="title" value="Title" />
        <TextInput
          id="title"
          v-model="form.title"
          type="text"
          class="mt-1 block w-full"
          required
          autofocus
        />
        <InputError :message="form.errors.title" class="mt-2" />
      </div>

      <div class="mt-4">
        <InputLabel for="content" value="Content" />
        <textarea
          id="content"
          v-model="form.content"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          rows="4"
          required
        ></textarea>
        <InputError :message="form.errors.content" class="mt-2" />
      </div>

      <div class="mt-6 flex justify-end">
        <SecondaryButton @click="closeModal">
          Cancel
        </SecondaryButton>

        <PrimaryButton
          class="ms-3"
          :class="{ 'opacity-25': form.processing }"
          :disabled="form.processing"
          @click="submit"
        >
          {{ note ? 'Save Changes' : 'Create Note' }}
        </PrimaryButton>
      </div>
    </div>
  </Modal>
</template>
