<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
