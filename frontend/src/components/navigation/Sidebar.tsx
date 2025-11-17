import { NavLink } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

const navItems = [
  { to: '/dashboard', label: 'Dashboard' },
  { to: '/inbox', label: 'Inbox' },
  { to: '/projects', label: 'Projects' },
  { to: '/contacts', label: 'Contacts' },
  { to: '/email-accounts', label: 'Email Accounts', roles: ['admin', 'manager'] },
  { to: '/admin/invites', label: 'Invites', roles: ['admin'] },
];

export const Sidebar = () => {
  const { user } = useAuth();

  const hasRole = (roles?: string[]) => {
    if (!roles || roles.length === 0) return true;
    return roles.some((role) => user?.roles?.includes(role));
  };

  return (
    <aside className="sidebar">
      <div className="sidebar__brand">
        <span className="sidebar__logo">AT</span>
        <div>
          <p className="sidebar__title">Arabia Talents</p>
          <span className="sidebar__subtitle">CRM</span>
        </div>
      </div>
      <nav className="sidebar__nav">
        {navItems
          .filter((item) => hasRole(item.roles))
          .map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) => (isActive ? 'sidebar__link sidebar__link--active' : 'sidebar__link')}
            >
              {item.label}
            </NavLink>
          ))}
      </nav>
    </aside>
  );
};
