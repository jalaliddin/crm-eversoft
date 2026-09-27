<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for business notifications. Channels come from the tenant's notification settings,
 * so each organization decides which events go to the database feed and/or e-mail.
 * Payloads are primitives computed at dispatch time: no tenant-scoped lazy loading in workers.
 * Future channels (Telegram, SMS, WhatsApp, Push) plug in by adding them to via().
 */
abstract class TenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(protected int $organizationId, protected array $payload) {}

    abstract public function eventKey(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    public function link(): ?string
    {
        return $this->payload['link'] ?? null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $organization = Organization::query()->find($this->organizationId);
        $channels = (array) ($organization?->setting('notifications.'.$this->eventKey()) ?? ['database']);

        return array_values(array_intersect($channels, ['database', 'mail']));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->eventKey(),
            'title' => $this->title(),
            'body' => $this->body(),
            'link' => $this->link(),
            'payload' => $this->payload,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->title())->line($this->body());

        if ($this->link() !== null) {
            $message->action('Open', rtrim((string) config('app.frontend_url'), '/').$this->link());
        }

        return $message;
    }
}
