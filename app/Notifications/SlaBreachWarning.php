<?php

namespace App\Notifications;

use App\Models\TicketSlaClock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketSlaClock $clock, public bool $breached) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->clock->ticket;
        $verb = $this->breached ? 'has breached' : 'is approaching';

        return (new MailMessage)
            ->subject(($this->breached ? '[BREACHED] ' : '[At risk] ')."Ticket {$ticket->ticket_number}")
            ->line("The {$this->clock->metric} SLA for {$ticket->ticket_number} ({$ticket->priority}) {$verb} its target.")
            ->action('View ticket', url('/tickets/'.$ticket->ticket_number));
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->clock->ticket;

        return [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'message' => ($this->breached ? 'SLA breached: ' : 'SLA at risk: ').$this->clock->metric,
        ];
    }
}
