<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Hr\Employee;
use App\Models\User;

/** CRM/HR quick search for the command palette. Tenant scope + per-module permissions apply. */
class GlobalSearchService
{
    /** @return list<array{type: string, id: int, title: string, subtitle: ?string, link: string}> */
    public function search(User $user, string $term, int $limit = 5): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $results = [];

        if ($user->hasPermission('customers.view')) {
            foreach (Customer::query()->search($term)->limit($limit)->get() as $c) {
                $results[] = ['type' => 'customer', 'id' => $c->id, 'title' => $c->display_name, 'subtitle' => $c->email ?? $c->phone, 'link' => '/crm/customers/'.$c->id];
            }
        }
        if ($user->hasPermission('leads.view')) {
            foreach (Lead::query()->visibleTo($user)->search($term)->limit($limit)->get() as $l) {
                $results[] = ['type' => 'lead', 'id' => $l->id, 'title' => $l->name, 'subtitle' => $l->status->label(), 'link' => '/crm/leads/'.$l->id];
            }
        }
        if ($user->hasPermission('deals.view')) {
            foreach (Deal::query()->visibleTo($user)->search($term)->limit($limit)->get() as $d) {
                $results[] = ['type' => 'deal', 'id' => $d->id, 'title' => $d->title, 'subtitle' => number_format((float) $d->value, 2).' '.$d->currency, 'link' => '/crm/deals/'.$d->id];
            }
        }
        if ($user->hasPermission('employees.view')) {
            foreach (Employee::query()->search($term)->limit($limit)->get() as $e) {
                $results[] = ['type' => 'employee', 'id' => $e->id, 'title' => $e->full_name, 'subtitle' => $e->employee_number, 'link' => '/hr/employees/'.$e->id];
            }
        }

        return $results;
    }
}
