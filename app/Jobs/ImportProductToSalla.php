<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\SallaProductMapper;
use App\Integrations\Salla\SallaClient;
use App\Models\Import;
use App\Models\ImportProduct;
use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportProductToSalla implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=3; public int $timeout=180;
    public function __construct(public readonly string $importProductId) {}
    public function handle(SallaProductMapper $mapper): void
    {
        $item=ImportProduct::query()->findOrFail($this->importProductId); $import=Import::query()->findOrFail($item->import_id); $merchant=Merchant::query()->findOrFail($import->merchant_id);
        $item->update(['import_status'=>'processing','current_step'=>'product']);
        try {
            $existing=DB::table('product_mappings')->where(['merchant_id'=>$merchant->id,'woocommerce_connection_id'=>$import->woocommerce_connection_id,'source_product_id'=>$item->source_product_id])->first();
            if($existing){$item->update(['destination_product_id'=>$existing->salla_product_id,'import_status'=>'completed','verification_status'=>'mapped','current_step'=>'done','last_error'=>null]);$this->recount($import);return;}
            $normalized=$item->normalized_data; $client=new SallaClient($merchant->access_token);
            $created=$client->createProduct($mapper->map($normalized)); $sallaId=(int)($created['id']??0); if($sallaId<1) throw new \RuntimeException('Salla did not return a product id.');
            $item->update(['destination_product_id'=>$sallaId,'current_step'=>'options']);
            foreach($mapper->options($normalized) as $option) $client->createOption($sallaId,$option);
            DB::table('product_mappings')->insert(['merchant_id'=>$merchant->id,'woocommerce_connection_id'=>$import->woocommerce_connection_id,'source_product_id'=>$item->source_product_id,'salla_product_id'=>$sallaId,'created_at'=>now(),'updated_at'=>now()]);
            $item->update(['destination_product_id'=>$sallaId,'import_status'=>'completed','verification_status'=>'created','current_step'=>'done','last_error'=>null]); $this->recount($import);
        } catch(Throwable $e){$item->update(['import_status'=>'failed','current_step'=>'failed','last_error'=>mb_substr($e->getMessage(),0,4000)]);$this->recount($import);throw $e;}
    }
    private function recount(Import $import): void
    {
        $q=ImportProduct::query()->where('import_id',$import->id);$completed=(clone $q)->where('import_status','completed')->count();$failed=(clone $q)->where('import_status','failed')->count();$pending=(clone $q)->whereIn('import_status',['pending','processing'])->count();
        $import->update(['completed_products'=>$completed,'failed_products'=>$failed,'status'=>$pending>0?'importing':($failed>0?'partial':'completed'),'import_completed_at'=>$pending>0?null:now()]);
    }
}
