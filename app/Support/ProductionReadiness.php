<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProductionReadiness
{
    /** @return array<string,array{ok:bool,detail:string}> */
    public function checks(): array
    {
        $checks=[];

        $checks['app_key']=$this->configured((string)config('app.key'),'APP_KEY');
        $checks['app_url']=$this->httpsUrl((string)config('app.url'));
        $checks['salla_client_id']=$this->configured((string)config('services.salla.client_id'),'SALLA_CLIENT_ID');
        $checks['salla_client_secret']=$this->configured((string)config('services.salla.client_secret'),'SALLA_CLIENT_SECRET');
        $checks['salla_webhook_secret']=$this->configured((string)config('services.salla.webhook_secret'),'SALLA_WEBHOOK_SECRET');

        try {
            DB::connection()->getPdo();
            $checks['database']=['ok'=>true,'detail'=>'connected'];

            foreach(['merchants','woocommerce_connections','imports','import_products','product_mappings','variant_mappings','jobs','failed_jobs','sessions','cache'] as $table){
                $present=Schema::hasTable($table);
                $checks['table_'.$table]=['ok'=>$present,'detail'=>$present?'present':'missing'];
            }
        } catch (\Throwable) {
            $checks['database']=['ok'=>false,'detail'=>'unavailable'];
        }

        $queue=(string)config('queue.default');
        $checks['queue']=['ok'=>$queue!=='sync','detail'=>$queue];

        foreach(['storage'=>storage_path(),'bootstrap_cache'=>base_path('bootstrap/cache')] as $name=>$path){
            $writable=is_writable($path);
            $checks[$name]=['ok'=>$writable,'detail'=>$writable?'writable':'not writable'];
        }

        return $checks;
    }

    public function ready(): bool
    {
        foreach($this->checks() as $check){
            if(!$check['ok']) return false;
        }

        return true;
    }

    /** @return array{ok:bool,detail:string} */
    private function configured(string $value,string $name): array
    {
        return ['ok'=>$value!=='','detail'=>$value!==''?'configured':$name.' missing'];
    }

    /** @return array{ok:bool,detail:string} */
    private function httpsUrl(string $value): array
    {
        $ok=str_starts_with(strtolower($value),'https://');
        return ['ok'=>$ok,'detail'=>$ok?'https':'APP_URL must use https'];
    }
}
