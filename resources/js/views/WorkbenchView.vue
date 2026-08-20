<template>
  <div class="flex-1 w-full h-full flex overflow-hidden">
    <!-- Left Pane: Event Feed -->
    <div class="w-96 flex-shrink-0 h-full">
      <EventFeed />
    </div>

    <!-- Right Pane: Monaco Editor & Replay Inspector -->
    <div class="flex-1 h-full min-w-0 bg-slate-950">
      <ReplayInspector />
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAnimaStore } from '../stores/useAnimaStore';
import EventFeed from '../components/EventFeed.vue';
import ReplayInspector from '../components/ReplayInspector.vue';

const store = useAnimaStore();

onMounted(() => {
  store.fetchEntries(1);
});
</script>
