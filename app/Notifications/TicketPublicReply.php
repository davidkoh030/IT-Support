<?php

namespace App\Notifications;

use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Only ever constructed for public comments (TicketService::addComment
 * gates the call) - internal notes must never reach this notification, so
 * their content can never leak into an email subject/preview/attachment.
 */
class TicketPublicReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketComment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->comment->ticket;

        return (new MailMessage)
            ->subject("New reply on ticket {$ticket->ticket_number}")
            ->line("{$this->comment->user->name} replied to {$ticket->ticket_number}: {$ticket->summary}")
            ->line($this->comment->body)
            ->action('View ticket', url('/tickets/'.$ticket->ticket_number));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->comment->ticket_id,
            'ticket_number' => $this->comment->ticket->ticket_number,
            'message' => "New reply from {$this->comment->user->name}",
        ];
    }
}
