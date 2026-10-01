<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Integrations\WooCommerce\SafeStoreUrl;
use App\Models\Merchant;
use App\Models\WooCommerceConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class WooCommerceAuthController extends Controller
{
 public function start(Request $request):RedirectResponse{$merchant=$request->attributes->get('merchant');abort_unless($merchant instanceof Merchant,401);$data=$request->validate(['store_url'=>['required','url','max:2048']]);$url=rtrim($data['store_url'],'/');SafeStoreUrl::assert($url);$nonce=Str::random(48);$request->session()->put("woo_auth.{$nonce}",['merchant_id'=>$merchant->id,'store_url'=>$url]);$query=http_build_query(['app_name'=>'WooCommerce to Salla','scope'=>'read','user_id'=>$nonce,'return_url'=>url('/'),'callback_url'=>route('woo.auth.callback')]);return redirect()->away($url.'/wc-auth/v1/authorize?'.$query);}
 public function callback(Request $request):JsonResponse{$data=$request->validate(['key_id'=>['required'],'user_id'=>['required','string'],'consumer_key'=>['required','string'],'consumer_secret'=>['required','string'],'key_permissions'=>['required','string']]);$pending=$request->session()->pull('woo_auth.'.$data['user_id']);abort_unless(is_array($pending)&&($pending['merchant_id']??0)>0,403,'Unknown WooCommerce authorization.');abort_unless(in_array($data['key_permissions'],['read','read_write'],true),422,'WooCommerce key must allow read access.');$url=$pending['store_url'];WooCommerceConnection::query()->updateOrCreate(['merchant_id'=>$pending['merchant_id'],'store_hash'=>hash('sha256',strtolower($url))],['store_url'=>$url,'consumer_key'=>$data['consumer_key'],'consumer_secret'=>$data['consumer_secret'],'status'=>'connected','last_verified_at'=>now()]);return response()->json(['received'=>true]);}
}
