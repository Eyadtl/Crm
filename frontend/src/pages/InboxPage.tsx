import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { fetchEmails } from '../api/emails';
import { Loader } from '../components/feedback/Loader';

const InboxPage = () => {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const navigate = useNavigate();

  const { data, isLoading } = useQuery({
    queryKey: ['emails', page, search],
    queryFn: () => fetchEmails({ page, search: search || undefined }),
  });

  if (import.meta.env.DEV) {
    console.debug('[Inbox] query params', { page, search });
    console.debug('[Inbox] response payload', data);
  }

  const emails = data?.data ?? [];
  const meta = data?.meta;

  const canGoPrev = meta ? meta.current_page > 1 : page > 1;
  const canGoNext = meta ? meta.current_page < meta.last_page : false;

  return (
    <div className="page">
      <div className="page__header">
        <h2>Inbox</h2>
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
      {isLoading ? (
        <Loader message="Syncing inbox..." />
      ) : (
        <div className="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>Direction</th>
                <th>Received</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {emails.map((email) => (
                <tr key={email.id}>
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
