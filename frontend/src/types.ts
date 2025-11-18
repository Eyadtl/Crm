export type User = {
  id: string;
  name: string;
  email: string;
  timezone: string;
  status: 'invited' | 'active' | 'disabled';
  roles?: string[];
};

export type AuthResponse = {
  access_token: string;
  token_type: string;
  expires_in: number;
  user: User;
};

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta?: PaginationMeta;
};

export type EmailAccount = {
  id: string;
  email: string;
  display_name?: string;
  imap_host: string;
  smtp_host: string;
  security_type: string;
  status: string;
  sync_state: string;
  sync_interval_minutes: number;
  last_synced_at?: string;
  sync_error?: string | null;
};

export type EmailParticipant = {
  id: string;
  type: 'sender' | 'to' | 'cc' | 'bcc';
  address: string;
  name?: string;
};

export type EmailAttachment = {
  id: string;
  filename: string;
  mime_type: string;
  size_bytes: number;
  status: string;
  storage_ref?: string;
};

export type Email = {
  id: string;
  email_account_id?: string;
  subject: string | null;
  snippet: string | null;
  direction: 'incoming' | 'outgoing';
  received_at?: string;
  sent_at?: string;
  body_ref?: string;
  body_cached_at?: string;
  has_attachments: boolean;
  project_flag: boolean;
  email_account?: EmailAccount;
  participants?: EmailParticipant[];
  attachments?: EmailAttachment[];
};

export type Project = {
  id: string;
  deal_name: string;
  product_name?: string | null;
  deal_status?: {
    id: string;
    name: string;
    is_terminal: boolean;
  };
  estimated_value?: string;
  expected_close_date?: string;
  notes?: string;
};

export type Contact = {
  id: string;
  name?: string;
  email: string;
  phone?: string;
  tags?: string[];
};

export type DashboardSummary = {
  total_projects: number;
  projects_by_status: { status: string; count: number }[];
  top_contacts: { contact_id: string; name?: string; email: string; email_count: number }[];
  latest_emails: Email[];
};

export type DataExport = {
  id: string;
  type: 'projects' | 'contacts';
  status: 'queued' | 'running' | 'ready' | 'failed';
  download_url?: string;
  created_at?: string;
};

export type InviteUserPayload = {
  name: string;
  email: string;
  roles: string[];
};

export type AcceptInvitePayload = {
  token: string;
  name: string;
  password: string;
};

export type SystemHealth = {
  queue_backlog: Record<string, number>;
  failing_accounts: {
    email_account_id: string;
    sync_state: string;
    sync_error?: string | null;
  }[];
  last_cron_run_at?: string;
  database_connections?: number | null;
  uptime_seconds?: number;
};
