<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WooCommerceConnection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'last_verified_at' => 'datetime',
        ];
    }
}
