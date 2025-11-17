import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import AppLayout from './layouts/AppLayout';
import LoginPage from './pages/LoginPage';
import AcceptInvitePage from './pages/AcceptInvitePage';
import DashboardPage from './pages/DashboardPage';
import InboxPage from './pages/InboxPage';
import ProjectsPage from './pages/ProjectsPage';
import ContactsPage from './pages/ContactsPage';
import EmailAccountsPage from './pages/EmailAccountsPage';
import InvitesPage from './pages/InvitesPage';
import EmailDetailPage from './pages/EmailDetailPage';
import { RequireAuth } from './components/RequireAuth';
import './index.css';

const App = () => (
  <BrowserRouter>
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/accept-invite" element={<AcceptInvitePage />} />
      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/inbox" element={<InboxPage />} />
          <Route path="/emails/:id" element={<EmailDetailPage />} />
          <Route path="/projects" element={<ProjectsPage />} />
          <Route path="/contacts" element={<ContactsPage />} />
          <Route path="/email-accounts" element={<EmailAccountsPage />} />
          <Route path="/admin/invites" element={<InvitesPage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  </BrowserRouter>
);

export default App;
