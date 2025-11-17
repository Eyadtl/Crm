import { apiClient } from './client';
import type { PaginatedResponse, Project } from '../types';

export const fetchProjects = async (params: Record<string, unknown> = {}): Promise<PaginatedResponse<Project>> => {
  const { data } = await apiClient.get('/projects', { params });
  return data;
};

export const createProject = async (payload: Partial<Project> & { deal_status_id: string }) => {
  const { data } = await apiClient.post('/projects', payload);
  return data.data ?? data;
};
