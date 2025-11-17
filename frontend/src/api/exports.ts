import { apiClient } from './client';
import type { DataExport } from '../types';

export const requestExport = async (type: 'projects' | 'contacts', filters: Record<string, unknown> = {}) => {
  const { data } = await apiClient.post(`/exports/${type}`, { filters });
  return data.data ?? (data as DataExport);
};

export const fetchExport = async (id: string): Promise<DataExport> => {
  const { data } = await apiClient.get(`/exports/${id}`);
  return data.data ?? data;
};
