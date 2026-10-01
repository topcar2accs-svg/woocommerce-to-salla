<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveSallaMerchant
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = (int) $request->session()->get('salla_merchant_id', 0);
        abort_unless($merchantId > 0, 401, 'Merchant session is not established.');
        $merchant = Merchant::query()->where('salla_merchant_id', $merchantId)->whereNotNull('access_token')->firstOrFail();
        $request->attributes->set('merchant', $merchant);
        return $next($request);
    }
}
