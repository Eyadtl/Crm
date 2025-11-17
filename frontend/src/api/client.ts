import axios from 'axios';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1';
const TOKEN_STORAGE_KEY = 'crm.token';

export const apiClient = axios.create({
  baseURL,
});

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_STORAGE_KEY);
  if (token) {
    config.headers = config.headers ?? {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const setAuthToken = (token?: string) => {
  if (token) {
    apiClient.defaults.headers.common.Authorization = `Bearer ${token}`;
    localStorage.setItem(TOKEN_STORAGE_KEY, token);
  } else {
    delete apiClient.defaults.headers.common.Authorization;
    localStorage.removeItem(TOKEN_STORAGE_KEY);
  }
};

export const bootstrapAuthToken = () => {
  const token = localStorage.getItem(TOKEN_STORAGE_KEY);
  if (token) {
    setAuthToken(token);
  }
  return token;
};
