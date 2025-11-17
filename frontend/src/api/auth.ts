import { apiClient } from './client';
import type { AcceptInvitePayload, AuthResponse, InviteUserPayload } from '../types';

export const loginRequest = async (payload: { email: string; password: string }): Promise<AuthResponse> => {
  const { data } = await apiClient.post('/auth/login', payload);
  return data;
};

export const logoutRequest = async (): Promise<void> => {
  await apiClient.post('/auth/logout');
};

export const inviteUserRequest = async (payload: InviteUserPayload) => {
  const { data } = await apiClient.post('/auth/invite', payload);
  return data;
};

export const acceptInviteRequest = async (payload: AcceptInvitePayload) => {
  const { data } = await apiClient.post('/auth/accept-invite', payload);
  return data;
};
