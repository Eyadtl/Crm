import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { bootstrapAuthToken, setAuthToken } from '../api/client';
import { loginRequest, logoutRequest } from '../api/auth';
import type { User } from '../types';

type AuthContextValue = {
  user: User | null;
  token: string | null;
  isBootstrapping: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

const USER_STORAGE_KEY = 'crm.user';

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const [token, setToken] = useState<string | null>(() => bootstrapAuthToken());
  const [user, setUser] = useState<User | null>(() => {
    const cached = localStorage.getItem(USER_STORAGE_KEY);
    return cached ? (JSON.parse(cached) as User) : null;
  });
  const [isBootstrapping, setIsBootstrapping] = useState(true);
  const queryClient = useQueryClient();

  useEffect(() => {
    setIsBootstrapping(false);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const response = await loginRequest({ email, password });
    setAuthToken(response.access_token);
    setToken(response.access_token);
    setUser(response.user);
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(response.user));
  }, []);

  const logout = useCallback(async () => {
    try {
      await logoutRequest();
    } catch (error) {
      // no-op if backend session already invalid
    }
    setAuthToken(undefined);
    setToken(null);
    setUser(null);
    localStorage.removeItem(USER_STORAGE_KEY);
    queryClient.clear();
  }, [queryClient]);

  const value = useMemo(
    () => ({
      user,
      token,
      login,
      logout,
      isBootstrapping,
    }),
    [user, token, login, logout, isBootstrapping],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};
