<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\WooProductTransformer;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\WooCommerceConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ScanWooCommerceCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $importId, public readonly int $page = 1) {}

    public function handle(WooProductTransformer $transformer): void
    {
        $import = DB::table('imports')->where('id', $this->importId)->first();
        if (!$import) {
            return;
        }

        $connection = WooCommerceConnection::query()->findOrFail($import->woocommerce_connection_id);
        $client = new WooCommerceClient($connection->store_url, $connection->consumer_key, $connection->consumer_secret);
        $products = $client->products($this->page, 100);

        foreach ($products as $product) {
            $normalized = $transformer->transform($product);
            DB::table('import_products')->upsert([[
                'id' => (string) Str::uuid(),
                'import_id' => $this->importId,
                'source_product_id' => $product['id'],
                'source_type' => $product['type'] ?? 'simple',
                'name' => $product['name'] ?? '',
                'sku' => $product['sku'] ?: null,
                'source_snapshot' => json_encode($product, JSON_THROW_ON_ERROR),
                'normalized_data' => json_encode($normalized, JSON_THROW_ON_ERROR),
                'validation_status' => 'ready',
                'import_status' => 'pending',
                'verification_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['import_id', 'source_product_id'], ['source_type','name','sku','source_snapshot','normalized_data','validation_status','updated_at']);
        }

        DB::table('imports')->where('id', $this->importId)->update([
            'status' => count($products) === 100 ? 'scanning' : 'scanned',
            'total_products' => DB::table('import_products')->where('import_id', $this->importId)->count(),
            'scan_started_at' => $import->scan_started_at ?: now(),
            'scan_completed_at' => count($products) < 100 ? now() : null,
            'updated_at' => now(),
        ]);

        if (count($products) === 100) {
            self::dispatch($this->importId, $this->page + 1);
        }
    }
}
