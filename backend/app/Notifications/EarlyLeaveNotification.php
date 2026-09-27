<?php

declare(strict_types=1);

namespace App\Notifications;

class EarlyLeaveNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'early_leave';
    }

    public function title(): string
    {
        return 'Early leave: '.$this->payload['employee'];
    }

    public function body(): string
    {
        return sprintf('%s left %d min early on %s.', $this->payload['employee'], $this->payload['minutes'], $this->payload['date']);
    }
}
