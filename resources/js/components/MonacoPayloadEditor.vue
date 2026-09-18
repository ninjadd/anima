<template>
  <div class="relative w-full h-full flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden transition-colors">
    <div class="h-9 px-4 bg-slate-100 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
      <div class="flex items-center space-x-2 font-mono">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <span class="font-medium text-slate-700 dark:text-slate-300">Payload Editor (JSON)</span>
      </div>
      <div class="flex items-center space-x-2">
        <button
          type="button"
          @click="formatDocument"
          class="px-2 py-0.5 rounded text-xs bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition"
          title="Format JSON"
        >
          Format
        </button>
      </div>
    </div>
    <div ref="editorContainer" class="flex-1 w-full h-full min-h-[350px]"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import * as monaco from 'monaco-editor/esm/vs/editor/editor.api';
import 'monaco-editor/esm/vs/language/json/monaco.contribution';

// Without this, Monaco tries to spin up its language/editor workers via a
// legacy AMD-style loader that doesn't exist in this ESM build, logs
// "Could not create web worker(s)", and throws when the json language
// service falls back to running in the main thread.
self.MonacoEnvironment = {
  getWorker(_workerId, label) {
    if (label === 'json') {
      return new Worker(
        new URL('monaco-editor/esm/vs/language/json/json.worker.js', import.meta.url),
        { type: 'module' }
      );
    }

    return new Worker(
      new URL('monaco-editor/esm/vs/editor/editor.worker.js', import.meta.url),
      { type: 'module' }
    );
  },
};

const props = defineProps({
  modelValue: {
    type: [String, Object, Array],
    default: '',
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
  language: {
    type: String,
    default: 'json',
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const editorContainer = ref(null);
let editorInstance = null;
let isUpdatingFromProp = false;

const stringifyValue = (val) => {
  if (val === null || val === undefined) return '';
  if (typeof val === 'object') {
    try {
      return JSON.stringify(val, null, 2);
    } catch {
      return String(val);
    }
  }
  return String(val);
};

const handleThemeChange = (e) => {
  const isDark = e.detail?.isDark ?? document.documentElement.classList.contains('dark');
  if (editorInstance) {
    monaco.editor.setTheme(isDark ? 'vs-dark' : 'vs');
  }
};

onMounted(() => {
  if (!editorContainer.value) return;

  const initialContent = stringifyValue(props.modelValue);
  const isDark = document.documentElement.classList.contains('dark');

  editorInstance = monaco.editor.create(editorContainer.value, {
    value: initialContent,
    language: props.language,
    theme: isDark ? 'vs-dark' : 'vs',
    readOnly: props.readOnly,
    automaticLayout: true,
    minimap: { enabled: false },
    scrollBeyondLastLine: false,
    fontSize: 13,
    lineNumbers: 'on',
    renderLineHighlight: 'all',
    tabSize: 2,
    wordWrap: 'on',
    formatOnPaste: true,
    padding: { top: 12, bottom: 12 },
  });

  editorInstance.onDidChangeModelContent(() => {
    if (isUpdatingFromProp) return;
    const value = editorInstance.getValue();
    emit('update:modelValue', value);
    emit('change', value);
  });

  window.addEventListener('anima-theme-changed', handleThemeChange);
});

watch(
  () => props.modelValue,
  (newVal) => {
    if (!editorInstance) return;

    const formatted = stringifyValue(newVal);
    if (editorInstance.getValue() !== formatted) {
      isUpdatingFromProp = true;
      const position = editorInstance.getPosition();
      editorInstance.setValue(formatted);
      if (position) {
        editorInstance.setPosition(position);
      }
      isUpdatingFromProp = false;
    }
  },
  { deep: true }
);

watch(
  () => props.readOnly,
  (newVal) => {
    if (editorInstance) {
      editorInstance.updateOptions({ readOnly: newVal });
    }
  }
);

const formatDocument = () => {
  if (!editorInstance) return;
  try {
    const raw = editorInstance.getValue();
    const parsed = JSON.parse(raw);
    editorInstance.setValue(JSON.stringify(parsed, null, 2));
  } catch {
    editorInstance.getAction('editor.action.formatDocument')?.run();
  }
};

const getValue = () => {
  return editorInstance ? editorInstance.getValue() : stringifyValue(props.modelValue);
};

defineExpose({
  formatDocument,
  getValue,
});

onBeforeUnmount(() => {
  window.removeEventListener('anima-theme-changed', handleThemeChange);
  if (editorInstance) {
    editorInstance.dispose();
    editorInstance = null;
  }
});
</script>
