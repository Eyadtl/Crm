import { apiClient } from './client';
import type { Email, PaginatedResponse } from '../types';

export const fetchEmails = async (params: Record<string, unknown> = {}): Promise<PaginatedResponse<Email>> => {
  const { data } = await apiClient.get('/emails', { params });
  return data;
};

export const fetchEmail = async (id: string): Promise<Email> => {
  const { data } = await apiClient.get(`/emails/${id}`);
  return data.data ?? data;
};

export const fetchEmailBody = async (id: string) => {
  const { data } = await apiClient.post(`/emails/${id}/fetch-body`);
  return data;
};

export const replyToEmail = (id: string, payload: Record<string, unknown>) =>
  apiClient.post(`/emails/${id}/reply`, payload);

export const forwardEmail = (id: string, payload: Record<string, unknown>) =>
  apiClient.post(`/emails/${id}/forward`, payload);
