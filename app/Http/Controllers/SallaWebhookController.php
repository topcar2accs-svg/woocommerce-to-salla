<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SallaWebhookController extends Controller
{
    public function __invoke(Request $request):JsonResponse
    {
        $this->verifySignature($request); $raw=$request->getContent(); $payload=$request->json()->all(); $event=(string)($payload['event']??''); $merchantId=(int)($payload['merchant']??0); $fingerprint=hash('sha256',$raw);
        if(DB::table('webhook_events')->where('fingerprint',$fingerprint)->exists()) return response()->json(['received'=>true,'duplicate'=>true]);
        DB::transaction(function()use($payload,$event,$merchantId,$fingerprint):void{
            if($event==='app.store.authorize'){$data=$payload['data']??[];abort_unless($merchantId>0&&!empty($data['access_token']),Response::HTTP_UNPROCESSABLE_ENTITY,'Invalid authorization payload.');Merchant::query()->updateOrCreate(['salla_merchant_id'=>$merchantId],['access_token'=>$data['access_token'],'refresh_token'=>$data['refresh_token']??null,'token_expires_at'=>isset($data['expires'])?now()->setTimestamp((int)$data['expires']):null]);}
            if($event==='app.uninstalled'&&$merchantId>0)Merchant::query()->where('salla_merchant_id',$merchantId)->update(['access_token'=>null,'refresh_token'=>null,'token_expires_at'=>null]);
            DB::table('webhook_events')->insert(['fingerprint'=>$fingerprint,'event'=>$event?:'unknown','merchant_id'=>$merchantId?:null,'processed_at'=>now()]);
        }); return response()->json(['received'=>true]);
    }
    private function verifySignature(Request $request):void
    {
        $secret=(string)config('services.salla.webhook_secret'); abort_if($secret==='',Response::HTTP_INTERNAL_SERVER_ERROR,'Webhook secret is not configured.'); $provided=(string)$request->header('X-Salla-Signature',''); $strategy=strtolower((string)$request->header('X-Salla-Security-Strategy','')); abort_unless($strategy==='signature'&&$provided!=='',Response::HTTP_UNAUTHORIZED,'Missing Salla signature.'); $expected=hash_hmac('sha256',$request->getContent(),$secret); abort_unless(hash_equals($expected,$provided),Response::HTTP_UNAUTHORIZED,'Invalid Salla signature.');
    }
}
