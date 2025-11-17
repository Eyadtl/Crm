import { type FormEvent, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { inviteUserRequest } from '../api/auth';

const InvitesPage = () => {
  const [form, setForm] = useState({ name: '', email: '', role: 'viewer' });

  const mutation = useMutation({
    mutationFn: () => inviteUserRequest({ name: form.name, email: form.email, roles: [form.role] }),
    onSuccess: () => setForm({ name: '', email: '', role: 'viewer' }),
  });

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    mutation.mutate();
  };

  return (
    <div className="page">
      <div className="page__header">
        <h2>Invite Users</h2>
      </div>
      <form className="card form-grid" onSubmit={handleSubmit}>
        <label>
          Name
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </label>
        <label>
          Email
          <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        </label>
        <label>
          Role
          <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="editor">Editor</option>
            <option value="viewer">Viewer</option>
          </select>
        </label>
        <button className="btn btn--primary" type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Sending...' : 'Send Invite'}
        </button>
        {mutation.isSuccess && <p className="hint">Invite sent.</p>}
        {mutation.isError && <p className="auth-error">Could not send invite.</p>}
      </form>
    </div>
  );
};

export default InvitesPage;
