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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SaasController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $merchant=$this->merchant($request);

        $imports=Import::query()
            ->where('merchant_id',$merchant->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'merchant'=>[
                'id'=>$merchant->id,
                'salla_merchant_id'=>$merchant->salla_merchant_id,
            ],
            'connections'=>WooCommerceConnection::query()
                ->where('merchant_id',$merchant->id)
                ->get(['id','store_url','status','last_verified_at']),
            'imports'=>$imports,
        ]);
    }

    public function connectWooCommerce(Request $request): JsonResponse
    {
        $merchant=$this->merchant($request);
        $data=$request->validate([
            'store_url'=>['required','url','max:2048'],
            'consumer_key'=>['required','string'],
            'consumer_secret'=>['required','string'],
        ]);

        $url=rtrim($data['store_url'],'/');
        (new WooCommerceClient($url,$data['consumer_key'],$data['consumer_secret']))->testConnection();

        $connection=WooCommerceConnection::query()->updateOrCreate(
            ['merchant_id'=>$merchant->id,'store_hash'=>hash('sha256',strtolower($url))],
            [
                'store_url'=>$url,
                'consumer_key'=>$data['consumer_key'],
                'consumer_secret'=>$data['consumer_secret'],
                'status'=>'connected',
                'last_verified_at'=>now(),
            ]
        );

        return response()->json(['id'=>$connection->id,'status'=>$connection->status],Response::HTTP_CREATED);
    }

    public function createImport(Request $request): JsonResponse
    {
        $merchant=$this->merchant($request);
        $data=$request->validate(['woocommerce_connection_id'=>['required','integer']]);
        $connection=WooCommerceConnection::query()
            ->where('merchant_id',$merchant->id)
            ->findOrFail($data['woocommerce_connection_id']);

        $import=Import::query()->create([
            'id'=>(string)Str::uuid(),
            'merchant_id'=>$merchant->id,
            'woocommerce_connection_id'=>$connection->id,
            'status'=>'scanning',
            'settings'=>[],
        ]);

        ScanWooCommerceCatalog::dispatch($import->id);

        return response()->json($import,Response::HTTP_ACCEPTED);
    }

    public function importProducts(Request $request,string $importId): JsonResponse
    {
        $merchant=$this->merchant($request);
        $import=Import::query()->where('merchant_id',$merchant->id)->findOrFail($importId);
        abort_unless(in_array($import->status,['scanned','partial','failed'],true),Response::HTTP_CONFLICT,'Import is not ready.');

        $ids=$request->input('product_ids');
        $products=ImportProduct::query()
            ->where('import_id',$import->id)
            ->whereIn('import_status',['pending','failed'])
            ->when(is_array($ids)&&$ids!==[],fn(Builder $q)=>$q->whereIn('id',$ids))
            ->get(['id']);

        abort_if($products->isEmpty(),Response::HTTP_CONFLICT,'There are no products ready to import.');

        $this->queueProducts($import,$products->pluck('id')->all());

        return response()->json(['queued'=>$products->count()],Response::HTTP_ACCEPTED);
    }

    public function retryFailed(Request $request,string $importId): JsonResponse
    {
        $merchant=$this->merchant($request);
        $import=Import::query()->where('merchant_id',$merchant->id)->findOrFail($importId);

        $failedIds=ImportProduct::query()
            ->where('import_id',$import->id)
            ->where('import_status','failed')
            ->pluck('id')
            ->all();

        abort_if($failedIds===[],Response::HTTP_CONFLICT,'There are no failed products to retry.');

        ImportProduct::query()
            ->whereIn('id',$failedIds)
            ->update(['import_status'=>'pending','last_error'=>null]);

        $this->queueProducts($import,$failedIds);

        return response()->json(['queued'=>count($failedIds)],Response::HTTP_ACCEPTED);
    }

    public function showImport(Request $request,string $importId): JsonResponse
    {
        $merchant=$this->merchant($request);
        $import=Import::query()->where('merchant_id',$merchant->id)->findOrFail($importId);
        $status=$request->query('status');

        $products=ImportProduct::query()
            ->where('import_id',$import->id)
            ->when(is_string($status)&&$status!=='',fn(Builder $q)=>$q->where('import_status',$status))
            ->orderByRaw("FIELD(import_status, 'failed', 'processing', 'pending', 'completed')")
            ->orderBy('name')
            ->paginate(50,[
                'id','source_product_id','destination_product_id','source_type','name','sku',
                'validation_status','import_status','verification_status','current_step','last_error','updated_at',
            ]);

        $summary=ImportProduct::query()
            ->where('import_id',$import->id)
            ->selectRaw('import_status, COUNT(*) as total')
            ->groupBy('import_status')
            ->pluck('total','import_status');

        return response()->json([
            'import'=>$import,
            'summary'=>[
                'pending'=>(int)($summary['pending']??0),
                'processing'=>(int)($summary['processing']??0),
                'completed'=>(int)($summary['completed']??0),
                'failed'=>(int)($summary['failed']??0),
            ],
            'products'=>$products,
        ]);
    }

    private function queueProducts(Import $import,array $productIds): void
    {
        $import->update([
            'status'=>'importing',
            'import_started_at'=>$import->import_started_at?:now(),
            'import_completed_at'=>null,
        ]);

        foreach($productIds as $productId){
            ImportProductToSalla::dispatch((string)$productId);
        }
    }

    private function merchant(Request $request): Merchant
    {
        $merchant=$request->attributes->get('merchant');
        abort_unless($merchant instanceof Merchant,Response::HTTP_UNAUTHORIZED,'Merchant context is not established.');

        return $merchant;
    }
}
