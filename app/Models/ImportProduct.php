<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ImportProduct extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'normalized_data' => 'array'];
    }
}
