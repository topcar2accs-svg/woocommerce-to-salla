<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SallaWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->verifySignature($request);

        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? '');

        if ($event === 'app.store.authorize') {
            $merchantId = $payload['merchant'] ?? null;
            $data = $payload['data'] ?? [];

            abort_unless($merchantId && !empty($data['access_token']), Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid authorization payload.');

            Merchant::query()->updateOrCreate(
                ['salla_merchant_id' => (int) $merchantId],
                [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'token_expires_at' => isset($data['expires']) ? now()->setTimestamp((int) $data['expires']) : null,
                ],
            );
        }

        if ($event === 'app.uninstalled' && isset($payload['merchant'])) {
            Merchant::query()->where('salla_merchant_id', (int) $payload['merchant'])->update([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ]);
        }

        return response()->json(['received' => true]);
    }

    private function verifySignature(Request $request): void
    {
        $secret = (string) config('services.salla.webhook_secret');
        abort_if($secret === '', Response::HTTP_INTERNAL_SERVER_ERROR, 'Webhook secret is not configured.');

        $provided = (string) $request->header('X-Salla-Signature', '');
        $strategy = strtolower((string) $request->header('X-Salla-Security-Strategy', ''));
        abort_unless($strategy === 'signature' && $provided !== '', Response::HTTP_UNAUTHORIZED, 'Missing Salla signature.');

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $provided), Response::HTTP_UNAUTHORIZED, 'Invalid Salla signature.');
    }
}
