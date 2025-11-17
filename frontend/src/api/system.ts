import { apiClient } from './client';
import type { SystemHealth } from '../types';

export const fetchSystemHealth = async (): Promise<SystemHealth> => {
  const { data } = await apiClient.get('/system/health');
  return data;
};
