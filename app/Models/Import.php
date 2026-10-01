<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Import extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'scan_started_at' => 'datetime',
            'scan_completed_at' => 'datetime',
            'import_started_at' => 'datetime',
            'import_completed_at' => 'datetime',
        ];
    }
}
