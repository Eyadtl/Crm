import { type FormEvent, useState } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { acceptInviteRequest } from '../api/auth';

const AcceptInvitePage = () => {
  const [searchParams] = useSearchParams();
  const [form, setForm] = useState({
    token: searchParams.get('token') ?? '',
    name: '',
    password: '',
    confirmPassword: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isComplete, setIsComplete] = useState(false);

  if (!form.token) {
    return <Navigate to="/login" replace />;
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);

    if (form.password.length < 8) {
      setError('Password must be at least 8 characters.');
      return;
    }

    if (form.password !== form.confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    try {
      setIsSubmitting(true);
      await acceptInviteRequest({
        token: form.token,
        name: form.name,
        password: form.password,
      });
      setIsComplete(true);
    } catch (err) {
      setError('Unable to accept invite. Please double-check your link or try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="auth-page">
      <form className="auth-card" onSubmit={handleSubmit}>
        {isComplete ? (
          <>
            <h2>Welcome aboard</h2>
            <p>Your account is ready. You can now sign in with your new password.</p>
            <Link to="/login" className="btn btn--primary">
              Go to login
            </Link>
          </>
        ) : (
          <>
            <h2>Finish account setup</h2>
            <p>Choose a password so you can access the CRM.</p>
            {error && <p className="auth-error">{error}</p>}
            <label>
              Full Name
              <input
                value={form.name}
                onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                required
              />
            </label>
            <label>
              Password
              <input
                type="password"
                value={form.password}
                onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
                required
                minLength={8}
              />
            </label>
            <label>
              Confirm Password
              <input
                type="password"
                value={form.confirmPassword}
                onChange={(e) => setForm((prev) => ({ ...prev, confirmPassword: e.target.value }))}
                required
                minLength={8}
              />
            </label>
            <button type="submit" className="btn btn--primary" disabled={isSubmitting}>
              {isSubmitting ? 'Activating...' : 'Activate Account'}
            </button>
          </>
        )}
      </form>
    </div>
  );
};

export default AcceptInvitePage;
