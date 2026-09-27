<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Services\Audit\AuditLogger;
use App\Support\OrganizationSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function show(): JsonResponse
    {
        return $this->ok(OrganizationResource::make($this->tenant->require()));
    }

    public function update(Request $request): JsonResponse
    {
        $organization = $this->tenant->require();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'alpha'],
            'locale' => ['sometimes', 'required', 'in:en,ru,uz'],
            ...OrganizationSettings::rules(),
        ]);

        $old = ['name' => $organization->name, 'timezone' => $organization->timezone, 'currency' => $organization->currency, 'settings' => $organization->settings];

        $organization->fill(array_intersect_key($data, array_flip(['name', 'timezone', 'currency', 'locale'])));
        if (isset($data['settings'])) {
            $clean = OrganizationSettings::sanitize($data['settings']);
            $settings = array_replace_recursive(OrganizationSettings::merge($organization->settings), $clean);
            // Lists are replaced wholesale, not merged index by index.
            if (isset($clean['work_week'])) {
                $settings['work_week'] = $clean['work_week'];
            }
            foreach ($clean['notifications'] ?? [] as $event => $channels) {
                $settings['notifications'][$event] = $channels;
            }
            $organization->settings = $settings;
        }
        $organization->save();

        $this->audit->log('settings.updated', $organization, $old, $data);

        return $this->ok(OrganizationResource::make($organization), 'Settings saved.');
    }

    public function logo(Request $request): JsonResponse
    {
        $organization = $this->tenant->require();
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024']]);

        $old = $organization->logo_path;
        $path = $request->file('logo')->storeAs('tenants/'.$organization->id.'/branding', Str::uuid().'.'.$request->file('logo')->guessExtension(), ['disk' => 'public']);
        $organization->update(['logo_path' => $path]);
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $this->ok(OrganizationResource::make($organization), 'Logo updated.');
    }
}
