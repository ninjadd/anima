import axios from 'axios';

const basePath = window.Anima?.path || 'anima';
const csrfToken = window.Anima?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const api = axios.create({
  baseURL: `/${basePath}/api`,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
  },
});

export default api;
