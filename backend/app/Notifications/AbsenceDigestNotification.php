<?php

declare(strict_types=1);

namespace App\Notifications;

class AbsenceDigestNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'employee_absent';
    }

    public function title(): string
    {
        return sprintf('%d employee(s) absent on %s', $this->payload['count'], $this->payload['date']);
    }

    public function body(): string
    {
        return 'Absent: '.implode(', ', $this->payload['names']).($this->payload['count'] > count($this->payload['names']) ? ' and others.' : '.');
    }
}
