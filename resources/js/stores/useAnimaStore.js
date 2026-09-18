import { defineStore } from 'pinia';
import { ref, reactive } from 'vue';
import api from '../services/api';

export const useAnimaStore = defineStore('anima', () => {
  const entries = ref([]);
  const activeEntry = ref(null);
  const isLoading = ref(false);
  const isReplaying = ref(false);
  const lastReplayResult = ref(null);

  const filters = reactive({
    search: '',
    method: '',
    tag: '',
    is_synthetic: null,
  });

  const pagination = reactive({
    total: 0,
    per_page: 25,
    current_page: 1,
    last_page: 1,
  });

  const fetchEntries = async (page = 1) => {
    isLoading.value = true;
    try {
      const params = {
        page,
        per_page: pagination.per_page,
        ...(filters.search ? { search: filters.search } : {}),
        ...(filters.method ? { method: filters.method } : {}),
        ...(filters.tag ? { tag: filters.tag } : {}),
        ...(filters.is_synthetic !== null ? { is_synthetic: filters.is_synthetic } : {}),
      };

      const { data } = await api.get('/entries', { params });
      entries.value = data.data || [];
      pagination.total = data.total || 0;
      pagination.per_page = data.per_page || 25;
      pagination.current_page = data.current_page || 1;
      pagination.last_page = data.last_page || 1;

      if (!activeEntry.value && entries.value.length > 0) {
        activeEntry.value = entries.value[0];
      }
    } catch (error) {
      console.error('Failed to fetch Anima entries:', error);
    } finally {
      isLoading.value = false;
    }
  };

  const loadEntry = async (id) => {
    isLoading.value = true;
    try {
      const { data } = await api.get(`/entries/${id}`);
      activeEntry.value = data;
      lastReplayResult.value = null;
    } catch (error) {
      console.error(`Failed to load entry ${id}:`, error);
    } finally {
      isLoading.value = false;
    }
  };

  const selectFirstEntry = () => {
    activeEntry.value = entries.value[0] || null;
    lastReplayResult.value = null;
  };

  const deleteEntry = async (id) => {
    try {
      await api.delete(`/entries/${id}`);
      entries.value = entries.value.filter((e) => e.id !== id);
      pagination.total = Math.max(0, pagination.total - 1);

      if (activeEntry.value?.id === id) {
        activeEntry.value = entries.value[0] || null;
      }
    } catch (error) {
      console.error(`Failed to delete entry ${id}:`, error);
    }
  };

  const clearEntries = async () => {
    try {
      await api.delete('/entries');
      entries.value = [];
      activeEntry.value = null;
      lastReplayResult.value = null;
      pagination.total = 0;
    } catch (error) {
      console.error('Failed to clear entries:', error);
    }
  };

  const triggerReplay = async ({ uri, method, headers, body }) => {
    isReplaying.value = true;
    lastReplayResult.value = null;
    try {
      const { data } = await api.post('/replay', {
        uri,
        method,
        headers,
        body,
      });

      lastReplayResult.value = data;
      return data;
    } catch (error) {
      console.error('Failed to dispatch synthetic replay:', error);
      const errResponse = error.response?.data;
      lastReplayResult.value = errResponse && errResponse.status_code ? errResponse : {
        status_code: error.response?.status || 500,
        headers: error.response?.headers || {},
        body: typeof errResponse === 'object' ? JSON.stringify(errResponse) : (errResponse || error.message || 'Synthetic replay failed'),
        duration_ms: 0,
        is_synthetic: true,
      };
      return lastReplayResult.value;
    } finally {
      isReplaying.value = false;
    }
  };

  return {
    entries,
    activeEntry,
    isLoading,
    isReplaying,
    lastReplayResult,
    filters,
    pagination,
    fetchEntries,
    loadEntry,
    selectFirstEntry,
    deleteEntry,
    clearEntries,
    triggerReplay,
  };
});
