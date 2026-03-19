<script setup>
import { usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import { watch } from 'vue';

const page = usePage();
const seenToastKeys = new Set();

const playChime = (tone) => {
  if (typeof window === 'undefined') {
    return;
  }

  const AudioContextClass = window.AudioContext || window.webkitAudioContext;

  if (!AudioContextClass) {
    return;
  }

  const context = new AudioContextClass();
  const now = context.currentTime;
  const notes = tone === 'error' ? [220, 174] : [587, 784];

  notes.forEach((frequency, index) => {
    const oscillator = context.createOscillator();
    const gainNode = context.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;

    const start = now + (index * 0.12);
    const end = start + 0.22;

    gainNode.gain.setValueAtTime(0.0001, start);
    gainNode.gain.exponentialRampToValueAtTime(0.08, start + 0.02);
    gainNode.gain.exponentialRampToValueAtTime(0.0001, end);

    oscillator.connect(gainNode);
    gainNode.connect(context.destination);

    oscillator.start(start);
    oscillator.stop(end);
  });

  window.setTimeout(() => {
    void context.close();
  }, 600);
};

const notify = (type, message) => {
  if (!message || typeof message !== 'string') {
    return;
  }

  const dedupeKey = `${page.url}:${type}:${message}`;

  if (seenToastKeys.has(dedupeKey)) {
    return;
  }

  seenToastKeys.add(dedupeKey);

  toast[type]?.(message, {
    duration: type === 'error' ? 6000 : 4000,
  });

  if (type === 'success' || type === 'error') {
    playChime(type);
  }
};

watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) {
      return;
    }

    notify('success', flash.success);
    notify('error', flash.error);
    notify('warning', flash.warning);
    notify('info', flash.info);
  },
  { deep: true, immediate: true }
);
</script>

<template>
  <Toaster
    position="top-right"
    rich-colors
    close-button
    expand
    :toast-options="{
      class: 'rounded-xl border border-gray-200 bg-white text-gray-900 shadow-lg',
      descriptionClass: 'text-gray-600',
    }"
  />
</template>
