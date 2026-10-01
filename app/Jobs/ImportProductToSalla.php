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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportProductToSalla implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries=3;
    public int $timeout=180;

    public function __construct(public readonly string $importProductId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('import-product:'.$this->importProductId))->releaseAfter(10)->expireAfter(300)];
    }

    public function handle(SallaProductMapper $mapper): void
    {
        $item=ImportProduct::query()->findOrFail($this->importProductId);
        $import=Import::query()->findOrFail($item->import_id);
        $merchant=Merchant::query()->findOrFail($import->merchant_id);
        $client=new SallaClient($merchant->access_token);

        $item->update(['import_status'=>'processing','current_step'=>'product','last_error'=>null]);

        try {
            $existing=DB::table('product_mappings')->where([
                'merchant_id'=>$merchant->id,
                'woocommerce_connection_id'=>$import->woocommerce_connection_id,
                'source_product_id'=>$item->source_product_id,
            ])->first();

            if($existing){
                $item->update([
                    'destination_product_id'=>$existing->salla_product_id,
                    'import_status'=>'completed',
                    'verification_status'=>'mapped',
                    'current_step'=>'done',
                    'last_error'=>null,
                ]);
                $this->recount($import);
                return;
            }

            $this->cleanupIncompleteAttempt($client,$item);

            $normalized=$item->normalized_data;
            $payload=$mapper->map($normalized);
            $categoryIds=$this->resolveCategories($client,$normalized['categories']??[]);
            if($categoryIds!==[]) $payload['categories']=array_map(static fn(int $id)=>['id'=>$id],$categoryIds);

            $created=$client->createProduct($payload);
            $sallaId=(int)($created['id']??0);
            if($sallaId<1) throw new \RuntimeException('Salla did not return a product id.');

            // Persist the remote identity immediately. If anything below fails, the next
            // attempt can remove this incomplete product before rebuilding it.
            $item->update(['destination_product_id'=>$sallaId,'current_step'=>'images']);

            $images=$mapper->images($normalized);
            foreach($images as $image){
                $client->attachImage($sallaId,$image['url'],(bool)$image['default'],(int)$image['sort'],(string)$image['alt']);
            }

            $item->update(['current_step'=>'options']);
            $optionValueIds=[];
            foreach($mapper->options($normalized) as $option){
                $createdOption=$client->createOption($sallaId,$option);
                $optionName=$this->key((string)($createdOption['name']??$option['name']??''));
                foreach($createdOption['values']??[] as $value){
                    $valueName=$this->key((string)($value['name']??$value['display_value']??''));
                    $valueId=(int)($value['id']??0);
                    if($optionName!==''&&$valueName!==''&&$valueId>0) $optionValueIds[$optionName][$valueName]=$valueId;
                }
            }

            if(($normalized['variants']??[])!==[]){
                $item->update(['current_step'=>'variants']);
                $sallaVariants=$client->variants($sallaId);
                $byValues=[];
                foreach($sallaVariants as $variant){
                    $ids=array_map('intval',$variant['related_option_values']??[]);
                    sort($ids,SORT_NUMERIC);
                    if($ids!==[]) $byValues[implode(':',$ids)]=$variant;
                }

                foreach($normalized['variants'] as $sourceVariant){
                    $ids=[];
                    foreach($sourceVariant['attributes']??[] as $attribute){
                        $name=$this->key((string)($attribute['name']??''));
                        $value=$this->key((string)($attribute['option']??''));
                        $id=(int)($optionValueIds[$name][$value]??0);
                        if($id>0) $ids[]=$id;
                    }
                    sort($ids,SORT_NUMERIC);
                    $signature=implode(':',$ids);
                    $target=$byValues[$signature]??null;
                    if(!$target) throw new \RuntimeException('Unable to match WooCommerce variation '.$sourceVariant['source_id'].' to a Salla variant.');

                    $variantPayload=array_filter([
                        'sku'=>$sourceVariant['sku']??null,
                        'price'=>isset($sourceVariant['regular_price'])&&$sourceVariant['regular_price']!==null?(float)$sourceVariant['regular_price']:null,
                        'sale_price'=>isset($sourceVariant['sale_price'])&&$sourceVariant['sale_price']!==null?(float)$sourceVariant['sale_price']:null,
                        'stock_quantity'=>$sourceVariant['stock_quantity']??null,
                        'weight'=>isset($sourceVariant['weight'])&&$sourceVariant['weight']!==null?(float)$sourceVariant['weight']:null,
                    ],static fn($v)=>$v!==null&&$v!=='');

                    if($variantPayload!==[]) $client->updateVariant((int)$target['id'],$variantPayload);

                    DB::table('variant_mappings')->updateOrInsert(
                        ['import_product_id'=>$item->id,'source_variant_id'=>(int)$sourceVariant['source_id']],
                        ['salla_variant_id'=>(int)$target['id'],'signature'=>$signature,'updated_at'=>now(),'created_at'=>now()]
                    );
                }
            }

            $item->update(['current_step'=>'verify']);
            $verified=$client->product($sallaId);
            if((int)($verified['id']??0)!==$sallaId) throw new \RuntimeException('Unable to verify the created Salla product.');
            if($images!==[]&&empty($verified['images'])&&empty($verified['main_image'])) throw new \RuntimeException('Salla product was created but its images could not be verified.');

            DB::transaction(function() use($merchant,$import,$item,$sallaId,$images): void {
                DB::table('product_mappings')->updateOrInsert(
                    [
                        'merchant_id'=>$merchant->id,
                        'woocommerce_connection_id'=>$import->woocommerce_connection_id,
                        'source_product_id'=>$item->source_product_id,
                    ],
                    [
                        'salla_product_id'=>$sallaId,
                        'updated_at'=>now(),
                        'created_at'=>now(),
                    ]
                );

                $item->update([
                    'destination_product_id'=>$sallaId,
                    'import_status'=>'completed',
                    'verification_status'=>$images===[]?'created_without_source_images':'verified',
                    'current_step'=>'done',
                    'last_error'=>null,
                ]);
            });

            $this->recount($import);
        } catch(Throwable $e){
            $item->update([
                'import_status'=>'failed',
                'current_step'=>'failed',
                'last_error'=>mb_substr($e->getMessage(),0,4000),
            ]);
            $this->recount($import);
            throw $e;
        }
    }

    private function cleanupIncompleteAttempt(SallaClient $client,ImportProduct $item): void
    {
        $orphanId=(int)($item->destination_product_id??0);
        if($orphanId<1) return;

        $item->update(['current_step'=>'cleanup']);
        $client->deleteProduct($orphanId);
        DB::table('variant_mappings')->where('import_product_id',$item->id)->delete();
        $item->update([
            'destination_product_id'=>null,
            'verification_status'=>'pending',
            'current_step'=>'product',
        ]);
    }

    private function resolveCategories(SallaClient $client,array $categories): array
    {
        $ids=[];
        foreach($categories as $sourceCategory){
            $name=trim((string)($sourceCategory['name']??''));
            if($name==='') continue;
            $matches=$client->searchCategories($name);
            $target=null;
            foreach($matches as $match){
                if($this->key((string)($match['name']??''))===$this->key($name)){
                    $target=$match;
                    break;
                }
            }
            if(!$target) $target=$client->createCategory(['name'=>$name,'status'=>'active']);
            $id=(int)($target['id']??0);
            if($id<1) throw new \RuntimeException("Salla did not return a category id for {$name}.");
            $ids[$id]=$id;
        }
        return array_values($ids);
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function recount(Import $import): void
    {
        $q=ImportProduct::query()->where('import_id',$import->id);
        $completed=(clone $q)->where('import_status','completed')->count();
        $failed=(clone $q)->where('import_status','failed')->count();
        $pending=(clone $q)->whereIn('import_status',['pending','processing'])->count();
        $import->update([
            'completed_products'=>$completed,
            'failed_products'=>$failed,
            'status'=>$pending>0?'importing':($failed>0?'partial':'completed'),
            'import_completed_at'=>$pending>0?null:now(),
        ]);
    }
}
