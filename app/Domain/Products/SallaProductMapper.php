<?php

declare(strict_types=1);

namespace App\Domain\Products;

final class SallaProductMapper
{
    public function map(array $n): array
    {
        $price=$n['sale_price'] ?? $n['regular_price'] ?? null;
        $quantity=$n['stock']['quantity'] ?? null;
        $images=array_values(array_filter(array_map(static function(array $image): ?array {
            $src=trim((string)($image['src']??''));
            if($src==='') return null;
            return ['original'=>$src,'thumbnail'=>$src,'alt'=>(string)($image['alt']??''),'default'=>(int)($image['position']??0)===0,'sort'=>(int)($image['position']??0)];
        },$n['images']??[])));
        return array_filter([
            'name'=>$n['name']??null,
            'sku'=>$n['sku']??null,
            'description'=>$n['description']??null,
            'price'=>$price!==null?(float)$price:null,
            'quantity'=>$quantity!==null?(int)$quantity:null,
            'weight'=>isset($n['weight'])&&$n['weight']!==null?(float)$n['weight']:null,
            'product_type'=>'product',
            'images'=>$images?:null,
        ],static fn($v)=>$v!==null&&$v!=='');
    }

    public function options(array $n): array
    {
        $options=[];
        foreach($n['attributes']??[] as $attribute){
            if(empty($attribute['variation'])) continue;
            $values=array_values(array_filter(array_map(static fn($value)=>trim((string)$value),$attribute['options']??[])));
            if($values===[]) continue;
            $options[]=['name'=>(string)($attribute['name']??'Option'),'required'=>true,'display_type'=>'text','values'=>array_map(static fn(string $value)=>['name'=>$value],$values)];
        }
        return $options;
    }
}
