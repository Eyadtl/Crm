<?php

namespace App\Mail;

use App\Models\AuthInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AuthInvitation $invitation)
    {
    }

    public function build(): self
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $link = "{$frontendUrl}/accept-invite?token={$this->invitation->token}";

        return $this->subject('You have been invited to Arabia Talents CRM')
            ->view('emails.invite')
            ->with([
                'link' => $link,
                'expiresAt' => $this->invitation->expires_at,
            ]);
    }
}
