import { useMutation, useQuery } from '@tanstack/react-query';
import { fetchDashboardSummary } from '../api/dashboard';
import { requestExport } from '../api/exports';
import { fetchSystemHealth } from '../api/system';
import { Loader } from '../components/feedback/Loader';

const DashboardPage = () => {
  const { data, isLoading } = useQuery({ queryKey: ['dashboard'], queryFn: fetchDashboardSummary });
  const { data: healthData, isLoading: isHealthLoading } = useQuery({
    queryKey: ['system-health'],
    queryFn: fetchSystemHealth,
    staleTime: 1000 * 60,
  });
  const exportMutation = useMutation({
    mutationFn: (type: 'projects' | 'contacts') => requestExport(type, {}),
  });

  if (isLoading || !data) {
    return <Loader message="Loading dashboard..." />;
  }

  return (
    <div className="page">
      <section className="grid">
        <div className="card">
          <p className="card__label">Total Projects</p>
          <h3 className="card__value">{data.total_projects}</h3>
        </div>
        {(data.projects_by_status ?? []).map((status) => (
          <div key={status.status} className="card">
            <p className="card__label">{status.status}</p>
            <h3 className="card__value">{status.count}</h3>
          </div>
        ))}
      </section>

      <section className="grid grid--2">
        <div className="card">
          <div className="card__header">
            <h4>Top Contacts</h4>
          </div>
          <ul className="list">
            {(data.top_contacts ?? []).map((contact) => (
              <li key={contact.contact_id}>
                <strong>{contact.name ?? contact.email}</strong>
                <span>{contact.email_count} emails</span>
              </li>
            ))}
          </ul>
        </div>
        <div className="card">
          <div className="card__header">
            <h4>Latest Emails</h4>
          </div>
          <ul className="list">
            {(data.latest_emails ?? []).map((email) => (
              <li key={email.id}>
                <strong>{email.subject ?? 'No subject'}</strong>
                <span>{email.received_at ? new Date(email.received_at).toLocaleString() : 'N/A'}</span>
              </li>
            ))}
          </ul>
        </div>
        <div className="card">
          <div className="card__header">
            <h4>Sync Health</h4>
            {healthData?.last_cron_run_at && (
              <span className="muted">
                Last cron: {new Date(healthData.last_cron_run_at).toLocaleString()}
              </span>
            )}
          </div>
          {isHealthLoading && <Loader message="Checking systems..." />}
          {!isHealthLoading && (
            <>
              <div className="grid grid--2">
                <div>
                  <p className="card__label">Queue backlog</p>
                  <ul className="list">
                    {Object.entries(healthData?.queue_backlog ?? {}).map(([queue, total]) => (
                      <li key={queue}>
                        {queue}: {total}
                      </li>
                    ))}
                    {Object.keys(healthData?.queue_backlog ?? {}).length === 0 && <li>All queues clear.</li>}
                  </ul>
                </div>
                <div>
                  <p className="card__label">DB Connections</p>
                  <h4>{healthData?.database_connections ?? 'N/A'}</h4>
                </div>
              </div>
              <div>
                <p className="card__label">Failing accounts</p>
                {(healthData?.failing_accounts ?? []).length === 0 ? (
                  <p className="muted">No accounts in warning/error.</p>
                ) : (
                  <ul className="list">
                    {healthData?.failing_accounts.map((account) => (
                      <li key={account.email_account_id}>
                        <strong>{account.email_account_id}</strong> — {account.sync_state}
                        {account.sync_error && <span className="muted"> ({account.sync_error})</span>}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </>
          )}
        </div>
      </section>

      <section className="card">
        <div className="card__header">
          <h4>CSV Exports</h4>
          <p>Request a fresh export and receive a download link once ready.</p>
        </div>
        <div className="export-actions">
          <button
            className="btn btn--primary"
            onClick={() => exportMutation.mutate('projects')}
            disabled={exportMutation.isPending}
          >
            Export Projects
          </button>
          <button
            className="btn btn--ghost"
            onClick={() => exportMutation.mutate('contacts')}
            disabled={exportMutation.isPending}
          >
            Export Contacts
          </button>
        </div>
        {exportMutation.data && (
          <p className="hint">
            Export requested. Check the Exports section in the Admin area to download when ready.
          </p>
        )}
      </section>
    </div>
  );
};

export default DashboardPage;
