import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { Loader } from './feedback/Loader';

export const RequireAuth = () => {
  const { token, isBootstrapping } = useAuth();
  const location = useLocation();

  if (isBootstrapping) {
    return <Loader message="Loading your workspace..." />;
  }

  if (!token) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <Outlet />;
};
