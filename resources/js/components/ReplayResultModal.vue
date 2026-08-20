<template>
  <Transition
    enter-active-class="transition ease-out duration-300 transform"
    enter-from-class="opacity-0 translate-x-full"
    enter-to-class="opacity-100 translate-x-0"
    leave-active-class="transition ease-in duration-200 transform"
    leave-from-class="opacity-100 translate-x-0"
    leave-to-class="opacity-0 translate-x-full"
  >
    <div
      v-if="show && result"
      class="fixed inset-y-0 right-0 w-full max-w-2xl bg-slate-900 border-l border-slate-800 shadow-2xl z-50 flex flex-col overflow-hidden"
    >
      <!-- Header -->
      <div class="px-6 py-4 bg-slate-900/90 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div
            :class="[
              'px-3 py-1 rounded-full text-xs font-mono font-bold border flex items-center space-x-1.5',
              getStatusBadgeClass(result.status_code)
            ]"
          >
            <span class="w-2 h-2 rounded-full" :class="getStatusDotClass(result.status_code)"></span>
            <span>HTTP {{ result.status_code }}</span>
          </div>

          <div class="flex items-center space-x-1 text-xs font-mono text-slate-400">
            <span>Duration:</span>
            <span class="text-white font-semibold">{{ result.duration_ms }} ms</span>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <button
            type="button"
            @click="copyResponse"
            class="px-2.5 py-1 text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded transition flex items-center space-x-1"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            <span>{{ copied ? 'Copied!' : 'Copy' }}</span>
          </button>

          <button
            type="button"
            @click="$emit('close')"
            class="p-1 text-slate-400 hover:text-white hover:bg-slate-800 rounded transition"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Error Callout if Status >= 400 -->
      <div
        v-if="result.status_code >= 400"
        class="mx-6 mt-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-start space-x-2.5"
      >
        <svg class="w-4 h-4 text-rose-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="font-semibold text-rose-200">Execution Error (HTTP {{ result.status_code }})</p>
          <p class="text-rose-300/80 mt-0.5">The application handled the synthetic request and returned an error response.</p>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="px-6 border-b border-slate-800 flex items-center space-x-6 text-xs mt-2">
        <button
          type="button"
          @click="activeTab = 'body'"
          :class="[
            'py-3 font-medium border-b-2 transition -mb-px',
            activeTab === 'body'
              ? 'text-indigo-400 border-indigo-500'
              : 'text-slate-400 border-transparent hover:text-slate-200'
          ]"
        >
          Response Body
        </button>

        <button
          type="button"
          @click="activeTab = 'headers'"
          :class="[
            'py-3 font-medium border-b-2 transition -mb-px flex items-center space-x-1.5',
            activeTab === 'headers'
              ? 'text-indigo-400 border-indigo-500'
              : 'text-slate-400 border-transparent hover:text-slate-200'
          ]"
        >
          <span>Response Headers</span>
          <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400 font-mono">
            {{ Object.keys(result.headers || {}).length }}
          </span>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 p-6 overflow-y-auto">
        <!-- Body View -->
        <div v-show="activeTab === 'body'" class="h-full">
          <div class="bg-slate-950 border border-slate-800 rounded-lg p-4 font-mono text-xs text-slate-300 overflow-x-auto min-h-[300px]">
            <pre>{{ formattedBody }}</pre>
          </div>
        </div>

        <!-- Headers View -->
        <div v-show="activeTab === 'headers'" class="space-y-4">
          <div class="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden">
            <table class="w-full text-xs font-mono divide-y divide-slate-800">
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="(val, key) in result.headers" :key="key" class="hover:bg-slate-800/20">
                  <td class="px-4 py-2 text-indigo-400 font-medium w-1/3">{{ key }}</td>
                  <td class="px-4 py-2 text-slate-300 select-all">
                    {{ Array.isArray(val) ? val.join(', ') : val }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  result: {
    type: Object,
    default: null,
  },
});

defineEmits(['close']);

const activeTab = ref('body');
const copied = ref(false);

const formattedBody = computed(() => {
  if (!props.result || props.result.body === null || props.result.body === undefined) {
    return 'null';
  }
  if (typeof props.result.body === 'object') {
    return JSON.stringify(props.result.body, null, 2);
  }
  try {
    const parsed = JSON.parse(props.result.body);
    return JSON.stringify(parsed, null, 2);
  } catch {
    return String(props.result.body);
  }
});

const getStatusBadgeClass = (statusCode) => {
  if (statusCode >= 200 && statusCode < 300) {
    return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
  }
  if (statusCode >= 400 && statusCode < 500) {
    return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
  }
  return 'bg-rose-500/10 text-rose-400 border-rose-500/20';
};

const getStatusDotClass = (statusCode) => {
  if (statusCode >= 200 && statusCode < 300) return 'bg-emerald-500';
  if (statusCode >= 400 && statusCode < 500) return 'bg-amber-500';
  return 'bg-rose-500';
};

const copyResponse = async () => {
  try {
    await navigator.clipboard.writeText(formattedBody.value);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (e) {
    console.error('Failed to copy response:', e);
  }
};
</script>
