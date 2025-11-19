import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { fetchEmails } from '../api/emails';
import { fetchEmailAccounts } from '../api/emailAccounts';
import { Loader } from '../components/feedback/Loader';

const InboxPage = () => {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [accountId, setAccountId] = useState('');
  const navigate = useNavigate();

  const { data: accountsData } = useQuery({
    queryKey: ['emailAccounts'],
    queryFn: fetchEmailAccounts,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['emails', page, search, accountId],
    queryFn: () => fetchEmails({ page, search: search || undefined, account_id: accountId || undefined }),
    staleTime: 0, // Force fresh data every time
    cacheTime: 0, // Don't cache
  });

  if (import.meta.env.DEV) {
    console.log('%c=== INBOX PAGE DEBUG ===', 'background: #222; color: #bada55; font-size: 14px; font-weight: bold;');
    console.log('Query params:', { page, search, accountId });
    console.log('Response payload:', data);
    console.log('Response meta:', data?.meta);
  }

  const emails = data?.data ?? [];
  const meta = data?.meta;
  const accounts = accountsData?.data ?? [];

  // Debug: Log email sorting
  if (import.meta.env.DEV && emails.length > 0) {
    console.log('%c=== EMAIL ORDER VERIFICATION ===', 'background: #0066cc; color: white; font-size: 14px; font-weight: bold;');
    console.log(`Total emails in response: ${emails.length}`);
    console.log('First 10 emails received from API:');
    console.table(emails.slice(0, 10).map((email, idx) => ({
      '#': idx + 1,
      Subject: email.subject?.substring(0, 50) || 'No subject',
      'Received At': email.received_at,
      'Account': email.email_account?.email || 'N/A',
      'How long ago': email.received_at ? new Date(email.received_at).toLocaleString() : 'N/A'
    })));

    // Check sort order
    const sortErrors: string[] = [];
    emails.forEach((email, idx) => {
      if (idx === 0) return;
      const current = new Date(email.received_at || email.created_at);
      const previous = new Date(emails[idx - 1].received_at || emails[idx - 1].created_at);
      if (current > previous) {
        sortErrors.push(`Position ${idx}: ${email.subject} (${email.received_at}) is NEWER than previous email`);
      }
    });

    if (sortErrors.length > 0) {
      console.error('%c❌ SORT ORDER ERRORS DETECTED:', 'color: red; font-weight: bold;');
      sortErrors.forEach(err => console.error(err));
    } else {
      console.log('%c✅ Sort order correct (newest to oldest)', 'color: green; font-weight: bold;');
    }
  }

  const canGoPrev = meta ? meta.current_page > 1 : page > 1;
  const canGoNext = meta ? meta.current_page < meta.last_page : false;

  return (
    <div className="page">
      <div className="page__header">
        <h2>Inbox</h2>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <select
            value={accountId}
            onChange={(event) => {
              setAccountId(event.target.value);
              setPage(1);
            }}
            style={{ padding: '0.5rem', borderRadius: '4px', border: '1px solid #ccc' }}
          >
            <option value="">All Accounts</option>
            {accounts.map((account) => (
              <option key={account.id} value={account.id}>
                {account.display_name || account.email}
              </option>
            ))}
          </select>
          <input
            type="search"
            placeholder="Search subject or snippet"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setPage(1);
            }}
          />
        </div>
      </div>
      {isLoading ? (
        <Loader message="Syncing inbox..." />
      ) : (
        <div className="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Account</th>
                <th>Subject</th>
                <th>Direction</th>
                <th>Received</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {emails.map((email) => (
                <tr key={email.id}>
                  <td>{email.email_account?.display_name || email.email_account?.email || '-'}</td>
                  <td>{email.subject ?? 'No subject'}</td>
                  <td>{email.direction}</td>
                  <td>{email.received_at ? new Date(email.received_at).toLocaleString() : 'Pending'}</td>
                  <td>
                    <button className="btn btn--ghost" onClick={() => navigate(`/emails/${email.id}`)}>
                      View
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {meta && (
            <div className="pagination">
              <button disabled={!canGoPrev} onClick={() => setPage((prev) => Math.max(prev - 1, 1))}>
                Previous
              </button>
              <span>
                Page {meta.current_page} of {meta.last_page}
              </span>
              <button disabled={!canGoNext} onClick={() => setPage((prev) => prev + 1)}>
                Next
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default InboxPage;
