<?php

declare(strict_types=1);

namespace App\Notifications;

class EmployeeLateNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'employee_late';
    }

    public function title(): string
    {
        return 'Late arrival: '.$this->payload['employee'];
    }

    public function body(): string
    {
        return sprintf('%s arrived %d min late on %s.', $this->payload['employee'], $this->payload['minutes'], $this->payload['date']);
    }
}
