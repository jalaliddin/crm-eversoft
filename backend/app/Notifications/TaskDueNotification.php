<?php

declare(strict_types=1);

namespace App\Notifications;

class TaskDueNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'task_due';
    }

    public function title(): string
    {
        return 'Task due: '.$this->payload['title'];
    }

    public function body(): string
    {
        return sprintf('"%s" is due at %s.', $this->payload['title'], $this->payload['due_at']);
    }
}
