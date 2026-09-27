<?php

declare(strict_types=1);

namespace App\Notifications;

class PayrollCalculatedNotification extends TenantNotification
{
    public function eventKey(): string
    {
        return 'payroll_calculated';
    }

    public function title(): string
    {
        return sprintf('Payroll calculated for %s', $this->payload['period']);
    }

    public function body(): string
    {
        return sprintf('%d records, net total %s %s.', $this->payload['records'], $this->payload['net_total'], $this->payload['currency']);
    }
}
