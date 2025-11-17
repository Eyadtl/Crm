import { Outlet } from 'react-router-dom';
import { Sidebar } from '../components/navigation/Sidebar';
import { TopBar } from '../components/navigation/TopBar';

const AppLayout = () => {
  return (
    <div className="app-shell">
      <Sidebar />
      <div className="app-shell__content">
        <TopBar />
        <main className="app-main">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AppLayout;
