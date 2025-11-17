import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchContacts, updateContact } from '../api/contacts';
import { Loader } from '../components/feedback/Loader';
import type { Contact } from '../types';

const ContactsPage = () => {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['contacts'],
    queryFn: () => fetchContacts({ per_page: 20 }),
  });
  const [selectedContact, setSelectedContact] = useState<Contact | null>(null);
  const [form, setForm] = useState({ name: '', phone: '', tags: '' });

  const mutation = useMutation({
    mutationFn: async () => {
      if (!selectedContact) {
        return;
      }
      const payload = {
        name: form.name || undefined,
        phone: form.phone || undefined,
        tags: form.tags
          ? form.tags
              .split(',')
              .map((tag) => tag.trim())
              .filter(Boolean)
          : undefined,
      };
      return updateContact(selectedContact.id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contacts'] });
      setSelectedContact(null);
    },
  });

  useEffect(() => {
    if (selectedContact) {
      setForm({
        name: selectedContact.name ?? '',
        phone: selectedContact.phone ?? '',
        tags: (selectedContact.tags ?? []).join(', '),
      });
    }
  }, [selectedContact]);

  if (isLoading || !data) {
    return <Loader message="Loading contacts..." />;
  }

  return (
    <div className="page">
      <div className="page__header">
        <h2>Contacts</h2>
      </div>
      <div className="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Tags</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {data.data.map((contact) => (
              <tr key={contact.id}>
                <td>{contact.name ?? '—'}</td>
                <td>{contact.email}</td>
                <td>{(contact.tags ?? []).join(', ') || '—'}</td>
                <td>
                  <button className="btn btn--ghost" onClick={() => setSelectedContact(contact)}>
                    Edit
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {selectedContact && (
        <form
          className="card form-grid"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate();
          }}
        >
          <div className="card__header">
            <h4>Edit Contact</h4>
            <button type="button" className="btn btn--ghost" onClick={() => setSelectedContact(null)}>
              Close
            </button>
          </div>
          <label>
            Name
            <input value={form.name} onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))} />
          </label>
          <label>
            Phone
            <input value={form.phone} onChange={(e) => setForm((prev) => ({ ...prev, phone: e.target.value }))} />
          </label>
          <label>
            Tags (comma separated)
            <input value={form.tags} onChange={(e) => setForm((prev) => ({ ...prev, tags: e.target.value }))} />
          </label>
          <button type="submit" className="btn btn--primary" disabled={mutation.isPending}>
            {mutation.isPending ? 'Saving...' : 'Save Changes'}
          </button>
          {mutation.isError && <p className="auth-error">Unable to save contact.</p>}
        </form>
      )}
    </div>
  );
};

export default ContactsPage;
