<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class SallaOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(48);
        $request->session()->put('salla_oauth_state', $state);
        $query = http_build_query([
            'client_id'=>config('services.salla.client_id'), 'response_type'=>'code',
            'redirect_uri'=>route('salla.oauth.callback'), 'state'=>$state,
        ]);
        return redirect()->away(rtrim((string) config('services.salla.accounts_base'), '/').'/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(hash_equals((string) $request->session()->pull('salla_oauth_state'), (string) $request->query('state')), 403, 'Invalid OAuth state.');
        $code = (string) $request->query('code');
        abort_if($code === '', 422, 'Missing authorization code.');
        $token = Http::asForm()->post(rtrim((string) config('services.salla.accounts_base'), '/').'/token', [
            'grant_type'=>'authorization_code','client_id'=>config('services.salla.client_id'),'client_secret'=>config('services.salla.client_secret'),
            'redirect_uri'=>route('salla.oauth.callback'),'code'=>$code,
        ])->throw()->json();
        $accessToken = (string) ($token['access_token'] ?? '');
        abort_if($accessToken === '', 502, 'Salla did not return an access token.');
        $user = Http::withToken($accessToken)->get(rtrim((string) config('services.salla.accounts_base'), '/').'/user/info')->throw()->json('data', []);
        $merchantId = (int) ($user['merchant']['id'] ?? $user['id'] ?? 0);
        abort_if($merchantId < 1, 502, 'Unable to resolve Salla merchant.');
        Merchant::query()->updateOrCreate(['salla_merchant_id'=>$merchantId], [
            'access_token'=>$accessToken,'refresh_token'=>$token['refresh_token'] ?? null,
            'token_expires_at'=>isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
        ]);
        $request->session()->put('salla_merchant_id', $merchantId);
        return redirect('/');
    }
}
