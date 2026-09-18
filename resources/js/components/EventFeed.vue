<template>
  <div class="h-full flex flex-col bg-slate-50 dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 transition-colors">
    <!-- Search and Filter Header -->
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Captured Events</h2>
          <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono">
            {{ store.pagination.total }}
          </span>
        </div>

        <div class="flex items-center space-x-1">
          <button
            type="button"
            @click="store.fetchEntries(1)"
            class="p-1.5 rounded text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 transition"
            title="Refresh Feed"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': store.isLoading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>

          <button
            type="button"
            @click="confirmClear"
            class="p-1.5 rounded text-slate-500 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 transition"
            title="Purge All Entries"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Search Input -->
      <div class="relative">
        <input
          v-model="store.filters.search"
          @input="handleSearch"
          type="text"
          placeholder="Filter by URI, payload, tag..."
          class="w-full pl-8 pr-3 py-1.5 text-xs bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-md text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
        />
        <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 absolute left-2.5 top-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <!-- Method Filters -->
      <div class="flex items-center space-x-1 overflow-x-auto pb-1 text-xs">
        <button
          v-for="method in ['ALL', 'POST', 'GET', 'PUT', 'DELETE', 'PATCH']"
          :key="method"
          type="button"
          @click="selectMethod(method)"
          :class="[
            'px-2 py-0.5 rounded text-[10px] font-mono font-medium transition',
            (method === 'ALL' && !store.filters.method) || store.filters.method === method
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          ]"
        >
          {{ method }}
        </button>
      </div>
    </div>

    <!-- Feed Items -->
    <div class="flex-1 overflow-y-auto divide-y divide-slate-200 dark:divide-slate-800/60">
      <div
        v-if="store.entries.length === 0"
        class="h-64 flex flex-col items-center justify-center p-6 text-center text-slate-400 dark:text-slate-500"
      >
        <svg class="w-10 h-10 mb-2 stroke-1 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">No Webhooks Intercepted</p>
        <p class="text-[11px] mt-1 text-slate-500 dark:text-slate-500">Send a request with <code class="text-indigo-600 dark:text-indigo-400 font-mono">anima.capture</code> middleware.</p>
      </div>

      <div
        v-for="entry in store.entries"
        :key="entry.id"
        @click="selectEntry(entry)"
        :class="[
          'p-3.5 cursor-pointer transition relative flex flex-col space-y-1.5',
          store.activeEntry?.id === entry.id
            ? 'bg-indigo-50 dark:bg-indigo-950/40 border-l-2 border-indigo-600 dark:border-indigo-500'
            : 'hover:bg-slate-100/80 dark:hover:bg-slate-800/40'
        ]"
      >
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center space-x-2">
            <span
              :class="[
                'px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold border',
                getMethodClass(entry.method)
              ]"
            >
              {{ entry.method }}
            </span>

            <span
              v-if="entry.response_status"
              :class="[
                'text-[11px] font-mono font-medium',
                entry.response_status >= 200 && entry.response_status < 300
                  ? 'text-emerald-600 dark:text-emerald-400'
                  : entry.response_status >= 400
                  ? 'text-rose-600 dark:text-rose-400'
                  : 'text-amber-600 dark:text-amber-400'
              ]"
            >
              {{ entry.response_status }}
            </span>

            <span
              v-if="entry.is_synthetic"
              class="text-[9px] px-1.5 py-0.2 rounded bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 font-mono"
            >
              REPLAY
            </span>
          </div>

          <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
            {{ formatTime(entry.created_at) }}
          </span>
        </div>

        <!-- URI -->
        <div class="text-xs text-slate-700 dark:text-slate-300 font-mono truncate" :title="entry.uri">
          {{ entry.uri }}
        </div>

        <!-- Tags & Metrics Footer -->
        <div class="flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500">
          <div class="flex items-center space-x-1 overflow-hidden">
            <span
              v-for="tag in entry.tags || []"
              :key="tag"
              class="px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono"
            >
              #{{ tag }}
            </span>
          </div>

          <span v-if="entry.duration_ms !== null" class="font-mono">
            {{ entry.duration_ms }}ms
          </span>
        </div>
      </div>
    </div>

    <!-- Pagination Footer -->
    <div
      v-if="store.pagination.last_page > 1"
      class="flex items-center justify-between px-4 py-2 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400"
    >
      <button
        type="button"
        :disabled="store.pagination.current_page <= 1 || store.isLoading"
        @click="store.fetchEntries(store.pagination.current_page - 1)"
        class="px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white disabled:opacity-40 disabled:hover:bg-transparent disabled:cursor-not-allowed transition"
      >
        Previous
      </button>

      <span class="font-mono">
        Page {{ store.pagination.current_page }} of {{ store.pagination.last_page }}
      </span>

      <button
        type="button"
        :disabled="store.pagination.current_page >= store.pagination.last_page || store.isLoading"
        @click="store.fetchEntries(store.pagination.current_page + 1)"
        class="px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white disabled:opacity-40 disabled:hover:bg-transparent disabled:cursor-not-allowed transition"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAnimaStore } from '../stores/useAnimaStore';

const store = useAnimaStore();
const router = useRouter();

const selectEntry = (entry) => {
  router.push({ name: 'workbench.entry', params: { id: entry.id } });
};

let searchTimeout = null;
const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    store.fetchEntries(1);
  }, 250);
};

const selectMethod = (method) => {
  store.filters.method = method === 'ALL' ? '' : method;
  store.fetchEntries(1);
};

const confirmClear = () => {
  if (confirm('Are you sure you want to clear all captured webhook entries?')) {
    store.clearEntries();
  }
};

const getMethodClass = (method) => {
  switch (method?.toUpperCase()) {
    case 'POST':
      return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20';
    case 'GET':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
    case 'PUT':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20';
    case 'DELETE':
      return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20';
    case 'PATCH':
      return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20';
    default:
      return 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-700';
  }
};

const formatTime = (isoString) => {
  if (!isoString) return '';
  try {
    const d = new Date(isoString);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch {
    return isoString;
  }
};
</script>
