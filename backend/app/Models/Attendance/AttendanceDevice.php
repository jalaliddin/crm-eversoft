<?php

declare(strict_types=1);

namespace App\Models\Attendance;

use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Enums\DeviceVendor;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceDevice extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'name', 'vendor', 'model', 'serial_number', 'type', 'connection_mode', 'ip_address', 'port', 'username',
        'encrypted_password', 'protocol', 'status', 'last_sync_at', 'last_event_at', 'last_sync_status', 'last_error', 'settings',
    ];

    protected $hidden = ['encrypted_password', 'bridge_token_hash'];

    /** @var list<string> never written to the audit log */
    protected array $auditExclude = ['encrypted_password', 'bridge_token_hash'];

    protected function casts(): array
    {
        return [
            'vendor' => DeviceVendor::class,
            'type' => DeviceType::class,
            'connection_mode' => DeviceConnectionMode::class,
            'status' => DeviceStatus::class,
            // Laravel encrypted cast: AES-256 with APP_KEY, never stored in plaintext.
            'encrypted_password' => 'encrypted',
            'port' => 'integer',
            'last_sync_at' => 'datetime',
            'last_event_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function baseUrl(): string
    {
        $port = $this->port ?: ($this->protocol === 'https' ? 443 : 80);

        return sprintf('%s://%s:%d', $this->protocol ?: 'http', $this->ip_address, $port);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(EmployeeDeviceIdentifier::class, 'device_id');
    }

    public function rawEvents(): HasMany
    {
        return $this->hasMany(AttendanceRawEvent::class, 'device_id');
    }
}
