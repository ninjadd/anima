<template>
  <div class="h-screen w-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col overflow-hidden select-none">
    <!-- Top Global Header -->
    <header class="h-13 border-b border-slate-200 dark:border-slate-800 px-5 flex items-center justify-between bg-white/80 dark:bg-slate-900/60 backdrop-blur flex-shrink-0">
      <div class="flex items-center space-x-3">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/25">
          A
        </div>
        <div class="flex items-baseline space-x-2">
          <span class="font-bold text-base tracking-tight text-slate-900 dark:text-white">Anima</span>
          <span class="text-[10px] px-1.5 py-0.2 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 font-mono">
            Workbench
          </span>
        </div>
      </div>

      <div class="flex items-center space-x-4 text-xs font-mono text-slate-500 dark:text-slate-400">
        <div class="flex items-center space-x-2">
          <span
            class="w-2 h-2 rounded-full"
            :class="statusDotClass"
          ></span>
          <span class="text-slate-700 dark:text-slate-300">{{ statusLabel }}</span>
        </div>
        <span class="text-slate-300 dark:text-slate-700">|</span>
        <div class="text-slate-500 dark:text-slate-400">
          Driver: <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ storageDriver }}</span>
        </div>
        <span class="text-slate-300 dark:text-slate-700">|</span>

        <!-- Theme Toggle Button -->
        <button
          type="button"
          @click="toggleTheme"
          class="p-1.5 rounded-md text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-200 dark:hover:bg-slate-800 transition"
          :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        >
          <!-- Sun Icon -->
          <svg v-if="isDark" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <!-- Moon Icon -->
          <svg v-else class="w-4 h-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
        </button>
      </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-1 w-full overflow-hidden flex">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAnimaStore } from './stores/useAnimaStore';

const store = useAnimaStore();
const isDark = ref(true);

onMounted(() => {
  isDark.value = document.documentElement.classList.contains('dark');
});

const toggleTheme = () => {
  isDark.value = !isDark.value;
  if (isDark.value) {
    document.documentElement.classList.add('dark');
    localStorage.setItem('anima-theme', 'dark');
  } else {
    document.documentElement.classList.remove('dark');
    localStorage.setItem('anima-theme', 'light');
  }
  window.dispatchEvent(new CustomEvent('anima-theme-changed', { detail: { isDark: isDark.value } }));
};

const storageDriver = computed(() => {
  return window.Anima?.storageDriver || 'database';
});

const pollingEnabled = computed(() => Number(window.Anima?.pollInterval ?? 8) > 0);

const statusLabel = computed(() => {
  if (!pollingEnabled.value) return 'Manual Refresh Only';
  return store.pollHealthy ? 'Listening for Webhooks' : 'Reconnecting…';
});

const statusDotClass = computed(() => {
  if (!pollingEnabled.value) return 'bg-slate-400 dark:bg-slate-600';
  return store.pollHealthy ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500';
});
</script>
