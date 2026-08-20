<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden flex flex-col transition-colors">
    <!-- Header Toolbar -->
    <div class="px-4 py-2.5 bg-slate-100 dark:bg-slate-900/90 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
      <div class="flex items-center space-x-2 font-medium text-slate-700 dark:text-slate-300">
        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
        <span>Request Headers ({{ headerRows.length }})</span>
      </div>

      <div class="flex items-center space-x-2">
        <button
          type="button"
          @click="addHeaderRow"
          class="px-2.5 py-1 rounded bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-600 dark:text-indigo-400 font-medium transition flex items-center space-x-1"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Add Header</span>
        </button>

        <button
          v-if="hasChanges"
          type="button"
          @click="resetToOriginal"
          class="px-2.5 py-1 rounded bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition"
          title="Reset headers to original captured values"
        >
          Reset
        </button>
      </div>
    </div>

    <!-- Headers Table / Grid -->
    <div class="p-3 max-h-[450px] overflow-y-auto space-y-2">
      <div
        v-for="(row, index) in headerRows"
        :key="index"
        class="flex items-center space-x-2 group"
      >
        <input
          v-model="row.key"
          @input="emitHeaders"
          type="text"
          placeholder="Header Key (e.g. Authorization)"
          class="w-2/5 px-3 py-1.5 text-xs font-mono bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded text-indigo-600 dark:text-indigo-400 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-indigo-500"
        />

        <input
          v-model="row.value"
          @input="emitHeaders"
          type="text"
          placeholder="Header Value"
          class="flex-1 px-3 py-1.5 text-xs font-mono bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-indigo-500"
        />

        <button
          type="button"
          @click="removeHeaderRow(index)"
          class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 rounded transition opacity-60 group-hover:opacity-100"
          title="Remove header"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div
        v-if="headerRows.length === 0"
        class="py-8 text-center text-xs text-slate-400 dark:text-slate-500 font-mono"
      >
        No headers set. Click "Add Header" above to include custom headers.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({}),
  },
  originalHeaders: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const headerRows = ref([]);

const transformHeadersToRows = (headersObj) => {
  if (!headersObj || typeof headersObj !== 'object') return [];
  return Object.entries(headersObj).map(([key, val]) => ({
    key,
    value: Array.isArray(val) ? val.join(', ') : String(val),
  }));
};

const transformRowsToHeaders = (rows) => {
  const result = {};
  for (const row of rows) {
    const k = row.key.trim();
    if (k !== '') {
      result[k] = row.value;
    }
  }
  return result;
};

watch(
  () => props.modelValue,
  (newHeaders) => {
    headerRows.value = transformHeadersToRows(newHeaders);
  },
  { immediate: true, deep: true }
);

const emitHeaders = () => {
  const result = transformRowsToHeaders(headerRows.value);
  emit('update:modelValue', result);
  emit('change', result);
};

const addHeaderRow = () => {
  headerRows.value.push({ key: '', value: '' });
  emitHeaders();
};

const removeHeaderRow = (index) => {
  headerRows.value.splice(index, 1);
  emitHeaders();
};

const resetToOriginal = () => {
  headerRows.value = transformHeadersToRows(props.originalHeaders);
  emitHeaders();
};

const hasChanges = computed(() => {
  const current = transformRowsToHeaders(headerRows.value);
  const original = props.originalHeaders || {};
  return JSON.stringify(current) !== JSON.stringify(original);
});

defineExpose({
  getHeaders: () => transformRowsToHeaders(headerRows.value),
  resetToOriginal,
});
</script>
