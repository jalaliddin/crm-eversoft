<?php

declare(strict_types=1);

namespace App\Notifications;

class LeadAssignedNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'lead_assigned';
    }

    public function title(): string
    {
        return 'Lead assigned: '.$this->payload['lead'];
    }

    public function body(): string
    {
        return sprintf('%s assigned the lead "%s" to you.', $this->payload['by'] ?? 'System', $this->payload['lead']);
    }
}
