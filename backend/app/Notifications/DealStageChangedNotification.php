<?php

declare(strict_types=1);

namespace App\Notifications;

class DealStageChangedNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'deal_stage_changed';
    }

    public function title(): string
    {
        return 'Deal moved: '.$this->payload['deal'];
    }

    public function body(): string
    {
        return sprintf('"%s" moved from %s to %s.', $this->payload['deal'], $this->payload['from'], $this->payload['to']);
    }
}
