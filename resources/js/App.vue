<template>
  <div class="h-screen w-screen bg-slate-950 text-slate-100 flex flex-col overflow-hidden select-none">
    <!-- Top Global Header -->
    <header class="h-13 border-b border-slate-800 px-5 flex items-center justify-between bg-slate-900/60 backdrop-blur flex-shrink-0">
      <div class="flex items-center space-x-3">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/25">
          A
        </div>
        <div class="flex items-baseline space-x-2">
          <span class="font-bold text-base tracking-tight text-white">Anima</span>
          <span class="text-[10px] px-1.5 py-0.2 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
            Workbench
          </span>
        </div>
      </div>

      <div class="flex items-center space-x-4 text-xs font-mono text-slate-400">
        <div class="flex items-center space-x-2">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-slate-300">Listening for Webhooks</span>
        </div>
        <span class="text-slate-700">|</span>
        <div class="text-slate-400">
          Driver: <span class="text-slate-200">{{ storageDriver }}</span>
        </div>
      </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-1 w-full overflow-hidden flex">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const storageDriver = computed(() => {
  return window.Anima?.storageDriver || 'database';
});
</script>
