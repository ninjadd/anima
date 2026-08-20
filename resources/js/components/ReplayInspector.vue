<template>
  <div v-if="store.activeEntry" class="h-full flex flex-col bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 overflow-hidden relative transition-colors">
    <!-- Top Action Bar -->
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 flex items-center justify-between gap-4">
      <div class="flex-1 flex items-center gap-2">
        <select
          v-model="replayMethod"
          class="px-3 py-1.5 text-xs font-mono font-bold bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md text-indigo-600 dark:text-indigo-400 focus:outline-none focus:border-indigo-500"
        >
          <option value="POST">POST</option>
          <option value="GET">GET</option>
          <option value="PUT">PUT</option>
          <option value="PATCH">PATCH</option>
          <option value="DELETE">DELETE</option>
        </select>

        <input
          v-model="replayUri"
          type="text"
          class="flex-1 px-3 py-1.5 text-xs font-mono bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md text-slate-900 dark:text-slate-200 focus:outline-none focus:border-indigo-500 placeholder-slate-400 dark:placeholder-slate-600"
          placeholder="/api/webhooks/endpoint or https://..."
        />
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="executeReplay"
          :disabled="store.isReplaying"
          class="px-4 py-1.5 text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-md flex items-center gap-2 shadow-lg shadow-indigo-600/25 transition cursor-pointer"
        >
          <svg
            v-if="store.isReplaying"
            class="animate-spin w-3.5 h-3.5"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ store.isReplaying ? 'Synthesizing...' : 'Synthesize & Replay' }}</span>
        </button>

        <button
          v-if="store.lastReplayResult"
          type="button"
          @click="showResultModal = true"
          class="px-3 py-1.5 text-xs font-mono font-medium rounded-md bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition flex items-center space-x-1.5"
          title="Open Replay Result Slide-Over"
        >
          <span
            class="w-2 h-2 rounded-full"
            :class="store.lastReplayResult.status_code >= 200 && store.lastReplayResult.status_code < 300 ? 'bg-emerald-500' : 'bg-rose-500'"
          ></span>
          <span>Result: {{ store.lastReplayResult.status_code }}</span>
        </button>

        <button
          type="button"
          @click="store.deleteEntry(store.activeEntry.id)"
          class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 rounded transition"
          title="Delete Entry"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="px-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-white/40 dark:bg-slate-900/20 text-xs">
      <div class="flex items-center space-x-4">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'py-2.5 font-medium border-b-2 transition -mb-px flex items-center space-x-1.5',
            activeTab === tab.id
              ? 'text-indigo-600 dark:text-indigo-400 border-indigo-600 dark:border-indigo-500'
              : 'text-slate-500 dark:text-slate-400 border-transparent hover:text-slate-900 dark:hover:text-slate-200'
          ]"
        >
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.badge !== undefined && tab.badge !== null"
            class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono"
          >
            {{ tab.badge }}
          </span>
        </button>
      </div>

      <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
        ID: <span class="text-slate-600 dark:text-slate-400">{{ store.activeEntry.id.substring(0, 8) }}...</span>
      </div>
    </div>

    <!-- Tab Content Panels -->
    <div class="flex-1 p-4 overflow-y-auto">
      <!-- 1. Payload Editor Tab -->
      <div v-show="activeTab === 'payload'" class="h-full flex flex-col">
        <MonacoPayloadEditor
          ref="editorRef"
          v-model="editablePayload"
          class="flex-1 min-h-[420px]"
        />
      </div>

      <!-- 2. Request Headers Tab -->
      <div v-show="activeTab === 'headers'" class="space-y-4">
        <HeaderInspector
          v-model="editableHeaders"
          :original-headers="store.activeEntry.headers || {}"
        />
      </div>

      <!-- 3. Original Response Tab -->
      <div v-show="activeTab === 'response'" class="space-y-4">
        <div class="flex items-center space-x-3 text-xs">
          <div class="flex items-center space-x-1.5">
            <span class="text-slate-500 dark:text-slate-400">Status:</span>
            <span
              :class="[
                'font-mono font-bold px-2 py-0.5 rounded text-xs',
                (store.activeEntry.response_status || 200) >= 200 && (store.activeEntry.response_status || 200) < 300
                  ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                  : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
              ]"
            >
              {{ store.activeEntry.response_status || 'N/A' }}
            </span>
          </div>

          <div v-if="store.activeEntry.duration_ms" class="flex items-center space-x-1.5">
            <span class="text-slate-500 dark:text-slate-400">Duration:</span>
            <span class="font-mono text-slate-800 dark:text-slate-300 font-semibold">{{ store.activeEntry.duration_ms }} ms</span>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-4 font-mono text-xs text-slate-800 dark:text-slate-300 overflow-x-auto">
          <pre>{{ formatResponseBody(store.activeEntry.response_body) }}</pre>
        </div>
      </div>
    </div>

    <!-- Replay Result Slide-Over Modal -->
    <ReplayResultModal
      :show="showResultModal"
      :result="store.lastReplayResult"
      @close="showResultModal = false"
    />
  </div>

  <!-- Empty State -->
  <div v-else class="h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 p-8 text-center bg-slate-100 dark:bg-slate-950">
    <svg class="w-12 h-12 mb-3 stroke-1 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
    </svg>
    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Select an Intercepted Webhook</p>
    <p class="text-xs mt-1 text-slate-500 dark:text-slate-600 max-w-sm">
      Choose an event from the feed on the left to inspect its headers, modify its JSON body in Monaco, and test replays.
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useAnimaStore } from '../stores/useAnimaStore';
import MonacoPayloadEditor from './MonacoPayloadEditor.vue';
import HeaderInspector from './HeaderInspector.vue';
import ReplayResultModal from './ReplayResultModal.vue';

const store = useAnimaStore();

const activeTab = ref('payload');
const replayMethod = ref('POST');
const replayUri = ref('');
const editablePayload = ref('');
const editableHeaders = ref({});
const editorRef = ref(null);
const showResultModal = ref(false);

const tabs = computed(() => [
  { id: 'payload', label: 'Payload Editor' },
  { id: 'headers', label: 'Request Headers', badge: Object.keys(editableHeaders.value || {}).length },
  { id: 'response', label: 'Original Response' },
]);

watch(
  () => store.activeEntry,
  (entry) => {
    if (entry) {
      replayMethod.value = entry.method || 'POST';
      replayUri.value = entry.uri || '';
      editablePayload.value = entry.payload || {};
      editableHeaders.value = { ...(entry.headers || {}) };
      showResultModal.value = false;
    }
  },
  { immediate: true, deep: true }
);

const executeReplay = async () => {
  if (!store.activeEntry) return;

  const payloadValue = editorRef.value ? editorRef.value.getValue() : editablePayload.value;

  let body = payloadValue;
  try {
    body = JSON.parse(payloadValue);
  } catch {
    body = payloadValue;
  }

  await store.triggerReplay({
    uri: replayUri.value,
    method: replayMethod.value,
    headers: editableHeaders.value,
    body,
  });

  showResultModal.value = true;
};

const formatResponseBody = (body) => {
  if (!body) return 'null';
  if (typeof body === 'object') {
    return JSON.stringify(body, null, 2);
  }
  try {
    const parsed = JSON.parse(body);
    return JSON.stringify(parsed, null, 2);
  } catch {
    return String(body);
  }
};
</script>
