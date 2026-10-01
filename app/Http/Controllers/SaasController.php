<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Jobs\ImportProductToSalla;
use App\Jobs\ScanWooCommerceCatalog;
use App\Models\Import;
use App\Models\ImportProduct;
use App\Models\Merchant;
use App\Models\WooCommerceConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SaasController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);
        return response()->json(['merchant'=>['id'=>$merchant->id,'salla_merchant_id'=>$merchant->salla_merchant_id], 'connections'=>WooCommerceConnection::query()->where('merchant_id',$merchant->id)->get(['id','store_url','status','last_verified_at']), 'imports'=>Import::query()->where('merchant_id',$merchant->id)->latest()->limit(20)->get()]);
    }
    public function connectWooCommerce(Request $request): JsonResponse
    {
        $merchant=$this->merchant($request); $data=$request->validate(['store_url'=>['required','url','max:2048'],'consumer_key'=>['required','string'],'consumer_secret'=>['required','string']]);
        (new WooCommerceClient($data['store_url'],$data['consumer_key'],$data['consumer_secret']))->testConnection();
        $connection=WooCommerceConnection::query()->updateOrCreate(['merchant_id'=>$merchant->id,'store_url'=>rtrim($data['store_url'],'/')],['consumer_key'=>$data['consumer_key'],'consumer_secret'=>$data['consumer_secret'],'status'=>'connected','last_verified_at'=>now()]);
        return response()->json(['id'=>$connection->id,'status'=>$connection->status],Response::HTTP_CREATED);
    }
    public function createImport(Request $request): JsonResponse
    {
        $merchant=$this->merchant($request); $data=$request->validate(['woocommerce_connection_id'=>['required','integer']]);
        $connection=WooCommerceConnection::query()->where('merchant_id',$merchant->id)->findOrFail($data['woocommerce_connection_id']);
        $import=Import::query()->create(['id'=>(string)Str::uuid(),'merchant_id'=>$merchant->id,'woocommerce_connection_id'=>$connection->id,'status'=>'scanning','settings'=>[]]);
        ScanWooCommerceCatalog::dispatch($import->id); return response()->json($import,Response::HTTP_ACCEPTED);
    }
    public function importProducts(Request $request,string $importId): JsonResponse
    {
        $merchant=$this->merchant($request); $import=Import::query()->where('merchant_id',$merchant->id)->findOrFail($importId);
        abort_unless(in_array($import->status,['scanned','partial','failed'],true),Response::HTTP_CONFLICT,'Import is not ready.');
        $ids=$request->input('product_ids'); $products=ImportProduct::query()->where('import_id',$import->id)->when(is_array($ids)&&$ids!==[],fn($q)=>$q->whereIn('id',$ids))->get(['id']);
        $import->update(['status'=>'importing','import_started_at'=>$import->import_started_at?:now()]); foreach($products as $product) ImportProductToSalla::dispatch($product->id);
        return response()->json(['queued'=>$products->count()],Response::HTTP_ACCEPTED);
    }
    public function showImport(Request $request,string $importId): JsonResponse
    {
        $merchant=$this->merchant($request); $import=Import::query()->where('merchant_id',$merchant->id)->findOrFail($importId);
        return response()->json(['import'=>$import,'products'=>ImportProduct::query()->where('import_id',$import->id)->paginate(50)]);
    }
    private function merchant(Request $request): Merchant
    {
        $merchant=$request->attributes->get('merchant'); abort_unless($merchant instanceof Merchant,Response::HTTP_UNAUTHORIZED,'Merchant context is not established.'); return $merchant;
    }
}
