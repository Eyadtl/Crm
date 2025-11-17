import { useMutation, useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { fetchEmail, fetchEmailBody } from '../api/emails';
import { Loader } from '../components/feedback/Loader';
import type { EmailAttachment } from '../types';

const EmailDetailPage = () => {
  const { id } = useParams();
  const emailId = id ?? '';

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['email', emailId],
    queryFn: () => fetchEmail(emailId),
    enabled: Boolean(emailId),
  });

  const fetchBodyMutation = useMutation({
    mutationFn: () => fetchEmailBody(emailId),
    onSuccess: () => refetch(),
  });

  if (isLoading || !data) {
    return <Loader message="Loading email..." />;
  }

  return (
    <div className="page">
      <h2>{data.subject ?? 'Untitled'}</h2>
      <p className="muted">{data.received_at ? new Date(data.received_at).toLocaleString() : 'Pending sync'}</p>
      <div className="card">
        <h4>Participants</h4>
        <ul className="list">
          {(data.participants ?? []).map((participant) => (
            <li key={participant.id}>
              {participant.type.toUpperCase()}: {participant.name ?? participant.address} ({participant.address})
            </li>
          ))}
        </ul>
      </div>
      <div className="card">
        <div className="card__header">
          <h4>Body</h4>
          <button className="btn btn--ghost" onClick={() => fetchBodyMutation.mutate()} disabled={fetchBodyMutation.isPending}>
            {fetchBodyMutation.isPending ? 'Fetching...' : 'Refresh Body'}
          </button>
        </div>
        {data.body_ref ? (
          <p>
            Body cached at {data.body_cached_at ? new Date(data.body_cached_at).toLocaleString() : ''}.{' '}
            <a className="link" href={data.body_ref} target="_blank" rel="noreferrer">
              View body
            </a>
          </p>
        ) : (
          <p>No cached body yet.</p>
        )}
      </div>
      <AttachmentsSection attachments={data.attachments ?? []} />
    </div>
  );
};

const AttachmentsSection = ({ attachments }: { attachments: EmailAttachment[] }) => {
  if (!attachments.length) {
    return null;
  }

  const formatSize = (size?: number) => {
    if (!size) return 'N/A';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  };

  return (
    <div className="card">
      <div className="card__header">
        <h4>Attachments</h4>
      </div>
      <ul className="list">
        {attachments.map((attachment) => (
          <li key={attachment.id} className="attachment">
            <div>
              <strong>{attachment.filename}</strong>
              <span className="muted">
                {attachment.mime_type} - {formatSize(attachment.size_bytes)}
              </span>
            </div>
            <div>
              <span className={`pill pill--${attachment.status}`}>{attachment.status}</span>
              {attachment.status === 'downloaded' && attachment.storage_ref && (
                <a className="btn btn--ghost" href={attachment.storage_ref} target="_blank" rel="noreferrer">
                  Open
                </a>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
};

export default EmailDetailPage;
