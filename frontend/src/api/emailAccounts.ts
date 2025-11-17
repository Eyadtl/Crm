import { apiClient } from './client';
import type { EmailAccount, PaginatedResponse } from '../types';

type CreateEmailAccountPayload = {
  email: string;
  display_name?: string;
  imap_host: string;
  imap_port: number;
  smtp_host: string;
  smtp_port: number;
  security_type: string;
  auth_type: string;
  credentials: { username: string; password: string };
};

export const fetchEmailAccounts = async (): Promise<PaginatedResponse<EmailAccount>> => {
  const { data } = await apiClient.get('/email-accounts');
  return data;
};

export const createEmailAccount = async (payload: CreateEmailAccountPayload) => {
  const { data } = await apiClient.post('/email-accounts', payload);
  return data.data ?? data;
};

export const testEmailAccount = async (id: string) => {
  const { data } = await apiClient.post(`/email-accounts/${id}/test`);
  return data;
};
