import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import AcceptInvitePage from '../AcceptInvitePage';
import { acceptInviteRequest } from '../../api/auth';

vi.mock('../../api/auth', () => ({
  acceptInviteRequest: vi.fn(),
}));

const renderWithRoute = (initialEntry = '/accept-invite?token=test-token') =>
  render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/accept-invite" element={<AcceptInvitePage />} />
        <Route path="/login" element={<p>Login Page</p>} />
      </Routes>
    </MemoryRouter>,
  );

describe('AcceptInvitePage', () => {
  const mockedAcceptInvite = vi.mocked(acceptInviteRequest);

  beforeEach(() => {
    vi.clearAllMocks();
    mockedAcceptInvite.mockReset();
  });

  it('shows validation error when passwords do not match', async () => {
    renderWithRoute();

    await userEvent.type(screen.getByLabelText(/Full Name/i), 'Jane Doe');
    await userEvent.type(screen.getByLabelText(/^Password/i), 'secret123');
    await userEvent.type(screen.getByLabelText(/Confirm Password/i), 'different');

    fireEvent.submit(screen.getByRole('button', { name: /Activate Account/i }));

    expect(await screen.findByText(/Passwords do not match/i)).toBeInTheDocument();
    expect(mockedAcceptInvite).not.toHaveBeenCalled();
  });

  it('submits invite acceptance when form is valid', async () => {
    mockedAcceptInvite.mockResolvedValue({ message: 'ok' } as never);

    renderWithRoute();

    await userEvent.type(screen.getByLabelText(/Full Name/i), 'John Smith');
    await userEvent.type(screen.getByLabelText(/^Password/i), 'secret123');
    await userEvent.type(screen.getByLabelText(/Confirm Password/i), 'secret123');

    fireEvent.submit(screen.getByRole('button', { name: /Activate Account/i }));

    await waitFor(() => expect(mockedAcceptInvite).toHaveBeenCalled());
    expect(await screen.findByText(/Your account is ready/i)).toBeInTheDocument();
  });

  it('redirects to login when token missing', () => {
    renderWithRoute('/accept-invite');
    expect(screen.getByText(/Login Page/i)).toBeInTheDocument();
  });
});
