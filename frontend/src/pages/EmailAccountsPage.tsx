import { type FormEvent, useState } from 'react';
import { isAxiosError } from 'axios';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createEmailAccount, fetchEmailAccounts, syncEmailAccount, testEmailAccount } from '../api/emailAccounts';
import { Loader } from '../components/feedback/Loader';

const defaultForm = {
  email: '',
  display_name: '',
  imap_host: '',
  imap_port: 993,
  smtp_host: '',
  smtp_port: 587,
  security_type: 'ssl',
  username: '',
  password: '',
};

const EmailAccountsPage = () => {
  const queryClient = useQueryClient();
  const [form, setForm] = useState(defaultForm);

  const { data, isLoading } = useQuery({
    queryKey: ['email-accounts'],
    queryFn: fetchEmailAccounts,
  });

  const mutation = useMutation({
    mutationFn: () =>
      createEmailAccount({
        email: form.email,
        display_name: form.display_name,
        imap_host: form.imap_host,
        imap_port: Number(form.imap_port),
        smtp_host: form.smtp_host,
        smtp_port: Number(form.smtp_port),
        security_type: form.security_type,
        auth_type: 'password',
        credentials: { username: form.username, password: form.password },
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['email-accounts'] });
      setForm(defaultForm);
    },
  });

  const testMutation = useMutation({
    mutationFn: (id: string) => testEmailAccount(id),
  });

  const [testResults, setTestResults] = useState<Record<string, { status: string; message?: string }>>({});
  const [syncResults, setSyncResults] = useState<Record<string, { status: string; message?: string }>>({});

  const syncMutation = useMutation({
    mutationFn: (id: string) => syncEmailAccount(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['emails'] });
    },
  });

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    mutation.mutate();
  };

  return (
    <div className="page">
      <div className="page__header">
        <h2>Email Accounts</h2>
      </div>
      <div className="grid grid--2">
        <div className="card">
          <h4>Active Mailboxes</h4>
          {isLoading ? (
            <Loader message="Loading accounts..." />
          ) : (
            <ul className="list">
              {(data?.data ?? []).map((account) => (
                <li key={account.id}>
                  <div>
                    <strong>{account.email}</strong>
                    <span className="muted">{account.imap_host}</span>
                  </div>
                  <div className="account-actions">
                    <span className={`pill pill--${account.sync_state}`}>{account.sync_state}</span>
                    <button
                      type="button"
                      className="btn btn--ghost"
                      onClick={async () => {
                        try {
                          const result = await testMutation.mutateAsync(account.id);
                          if (import.meta.env.DEV) {
                            console.debug('[EmailAccounts] test success', { id: account.id, result });
                          }
                          setTestResults((prev) => ({
                            ...prev,
                            [account.id]: { status: result.status, message: result.message },
                          }));
                        } catch (error) {
                          let message = 'Unable to test mailbox.';
                          if (isAxiosError(error) && error.response?.data) {
                            message =
                              (error.response.data.message as string | undefined) ??
                              (error.response.data.error as string | undefined) ??
                              message;
                          }
                          if (import.meta.env.DEV) {
                            console.error('[EmailAccounts] test failed', { id: account.id, error, message });
                          }
                          setTestResults((prev) => ({
                            ...prev,
                            [account.id]: { status: 'failed', message },
                          }));
                        }
                      }}
                      disabled={testMutation.isPending && testMutation.variables === account.id}
                    >
                      {testMutation.isPending && testMutation.variables === account.id ? 'Testing...' : 'Test'}
                    </button>
                    <button
                      type="button"
                      className="btn btn--ghost"
                      onClick={async () => {
                        try {
                          const result = await syncMutation.mutateAsync(account.id);
                          if (import.meta.env.DEV) {
                            console.debug('[EmailAccounts] sync success', { id: account.id, result });
                          }
                          setSyncResults((prev) => ({
                            ...prev,
                            [account.id]: { status: 'passed', message: result.message },
                          }));
                        } catch (error) {
                          let message = 'Unable to sync mailbox.';
                          if (isAxiosError(error) && error.response?.data) {
                            message =
                              (error.response.data.message as string | undefined) ??
                              (error.response.data.error as string | undefined) ??
                              message;
                          }
                          if (import.meta.env.DEV) {
                            console.error('[EmailAccounts] sync failed', { id: account.id, error, message });
                          }
                          setSyncResults((prev) => ({
                            ...prev,
                            [account.id]: { status: 'failed', message },
                          }));
                        }
                      }}
                      disabled={syncMutation.isPending && syncMutation.variables === account.id}
                    >
                      {syncMutation.isPending && syncMutation.variables === account.id ? 'Syncing...' : 'Sync now'}
                    </button>
                  </div>
                  {testResults[account.id] && (
                    <p className={`hint hint--${testResults[account.id].status}`}>
                      {testResults[account.id].status === 'passed' ? 'Connectivity OK' : 'Check settings'}
                      {testResults[account.id].message ? ` - ${testResults[account.id].message}` : ''}
                    </p>
                  )}
                  {syncResults[account.id] && (
                    <p className={`hint hint--${syncResults[account.id].status}`}>
                      {syncResults[account.id].status === 'passed' ? 'Sync complete' : 'Sync failed'}
                      {syncResults[account.id].message ? ` - ${syncResults[account.id].message}` : ''}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
        <form className="card form-grid" onSubmit={handleSubmit}>
          <h4>Add Mailbox</h4>
          <label>
            Email
            <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
          </label>
          <label>
            Display Name
            <input value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })} />
          </label>
          <label>
            IMAP Host
            <input value={form.imap_host} onChange={(e) => setForm({ ...form, imap_host: e.target.value })} required />
          </label>
          <label>
            IMAP Port
            <input
              type="number"
              value={form.imap_port}
              onChange={(e) => setForm({ ...form, imap_port: Number(e.target.value) })}
            />
          </label>
          <label>
            SMTP Host
            <input value={form.smtp_host} onChange={(e) => setForm({ ...form, smtp_host: e.target.value })} required />
          </label>
          <label>
            SMTP Port
            <input
              type="number"
              value={form.smtp_port}
              onChange={(e) => setForm({ ...form, smtp_port: Number(e.target.value) })}
            />
          </label>
          <label>
            Security
            <select value={form.security_type} onChange={(e) => setForm({ ...form, security_type: e.target.value })}>
              <option value="ssl">SSL</option>
              <option value="tls">TLS</option>
              <option value="starttls">STARTTLS</option>
            </select>
          </label>
          <label>
            Username
            <input value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} required />
          </label>
          <label>
            App Password
            <input
              type="password"
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
              required
            />
          </label>
        <button type="submit" className="btn btn--primary" disabled={mutation.isPending}>
          {mutation.isPending ? 'Saving...' : 'Save Mailbox'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default EmailAccountsPage;
