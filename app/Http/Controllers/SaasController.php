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
use Illuminate\Validation\ValidationException;
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

        $data=$request->validate([
            'product_ids'=>['required','array','min:1','max:500'],
            'product_ids.*'=>['required','uuid','distinct'],
        ]);

        $requestedIds=array_values($data['product_ids']);
        $products=ImportProduct::query()
            ->where('import_id',$import->id)
            ->whereIn('import_status',['pending','failed'])
            ->whereIn('id',$requestedIds)
            ->get(['id']);

        if($products->count()!==count($requestedIds)){
            throw ValidationException::withMessages([
                'product_ids'=>['Some selected products do not belong to this import or are no longer eligible to run. Refresh the report and select again.'],
            ]);
        }

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
        $search=trim((string)$request->query('q',''));
        $perPage=max(10,min(100,(int)$request->query('per_page',50)));

        $products=ImportProduct::query()
            ->where('import_id',$import->id)
            ->when(is_string($status)&&$status!=='',fn(Builder $q)=>$q->where('import_status',$status))
            ->when($search!=='',function(Builder $q) use($search): void {
                $q->where(function(Builder $sub) use($search): void {
                    $sub->where('name','like','%'.$search.'%')
                        ->orWhere('sku','like','%'.$search.'%')
                        ->orWhere('source_product_id',$search);
                });
            })
            ->orderByRaw("FIELD(import_status, 'failed', 'processing', 'pending', 'completed')")
            ->orderBy('name')
            ->paginate($perPage,[
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
