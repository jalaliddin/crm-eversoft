<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PlatformRole: string
{
    use EnumHelpers;

    case PlatformSuperAdmin = 'platform_super_admin';
    case PlatformSupport = 'platform_support';

    public function label(): string
    {
        return match ($this) {
            self::PlatformSuperAdmin => 'Platform super admin',
            self::PlatformSupport => 'Platform support',
        };
    }
}
