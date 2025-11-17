import { apiClient } from './client';
import type { Contact, PaginatedResponse } from '../types';

export const fetchContacts = async (params: Record<string, unknown> = {}): Promise<PaginatedResponse<Contact>> => {
  const { data } = await apiClient.get('/contacts', { params });
  return data;
};

export const updateContact = async (id: string, payload: Partial<Contact>) => {
  const { data } = await apiClient.patch(`/contacts/${id}`, payload);
  return data.data ?? data;
};
