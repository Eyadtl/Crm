import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

export const TopBar = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <header className="topbar">
      <div>
        <h1 className="topbar__title">Inbox Control Center</h1>
        <p className="topbar__subtitle">Monitor sync health, link projects, and act on conversations.</p>
      </div>
      <div className="topbar__actions">
        <span className="topbar__user">{user?.name}</span>
        <button type="button" onClick={handleLogout} className="btn btn--ghost">
          Logout
        </button>
      </div>
    </header>
  );
};
