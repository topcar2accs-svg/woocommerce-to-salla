<?php

declare(strict_types=1);

namespace App\Domain\Products;

final class SallaProductMapper
{
    public function map(array $n): array
    {
        $price=$n['sale_price'] ?? $n['regular_price'] ?? null;
        $quantity=$n['stock']['quantity'] ?? null;

        return array_filter([
            'name'=>$n['name']??null,
            'sku'=>$n['sku']??null,
            'description'=>$n['description']??null,
            'price'=>$price!==null?(float)$price:null,
            'quantity'=>$quantity!==null?(int)$quantity:null,
            'weight'=>isset($n['weight'])&&$n['weight']!==null?(float)$n['weight']:null,
            'product_type'=>'product',
        ],static fn($v)=>$v!==null&&$v!=='');
    }

    public function images(array $n): array
    {
        $images=[];
        foreach($n['images']??[] as $image){
            $src=trim((string)($image['src']??''));
            if($src==='') continue;
            $images[$src]=[
                'url'=>$src,
                'alt'=>(string)($image['alt']??''),
                'default'=>count($images)===0,
                'sort'=>count($images)+1,
            ];
        }

        foreach($n['variants']??[] as $variant){
            $image=$variant['image']??null;
            if(!is_array($image)) continue;
            $src=trim((string)($image['src']??''));
            if($src===''||isset($images[$src])) continue;
            $images[$src]=[
                'url'=>$src,
                'alt'=>(string)($image['alt']??($n['name']??'')),
                'default'=>false,
                'sort'=>count($images)+1,
            ];
        }

        return array_slice(array_values($images),0,10);
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
