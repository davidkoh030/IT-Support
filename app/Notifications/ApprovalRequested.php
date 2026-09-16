<?php

namespace App\Notifications;

use App\Models\Approval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Approval $approval) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->approval->approvable;

        return (new MailMessage)
            ->subject("Approval needed: {$ticket->ticket_number}")
            ->line("Your {$this->approval->approver_role} approval is requested for {$ticket->ticket_number}: {$ticket->summary}")
            ->action('Review request', url('/tickets/'.$ticket->ticket_number));
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->approval->approvable;

        return [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'message' => 'Your approval is requested',
        ];
    }
}
