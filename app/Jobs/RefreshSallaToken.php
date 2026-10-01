<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

final class RefreshSallaToken implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=3;
    public function __construct(public readonly int $merchantId) {}
    public function handle(): void
    {
        $merchant=Merchant::query()->findOrFail($this->merchantId); if (!$merchant->refresh_token) return;
        $token=Http::asForm()->post(rtrim((string)config('services.salla.accounts_base'),'/').'/token',['grant_type'=>'refresh_token','refresh_token'=>$merchant->refresh_token,'client_id'=>config('services.salla.client_id'),'client_secret'=>config('services.salla.client_secret')])->throw()->json();
        $merchant->update(['access_token'=>$token['access_token'],'refresh_token'=>$token['refresh_token']??$merchant->refresh_token,'token_expires_at'=>isset($token['expires_in'])?now()->addSeconds((int)$token['expires_in']):null]);
    }
}
