import { apiClient } from './client';
import type { DashboardSummary } from '../types';

export const fetchDashboardSummary = async (): Promise<DashboardSummary> => {
  const { data } = await apiClient.get('/dashboards/summary');
  return data.data ?? data;
};
