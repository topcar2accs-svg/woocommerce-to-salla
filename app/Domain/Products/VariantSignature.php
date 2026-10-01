<?php

declare(strict_types=1);

namespace App\Domain\Products;

final class VariantSignature
{
    public static function make(array $attributes): string
    {
        $pairs = [];
        foreach ($attributes as $attribute) {
            $name = self::normalize((string) ($attribute['name'] ?? ''));
            $value = self::normalize((string) ($attribute['option'] ?? $attribute['value'] ?? ''));
            if ($name !== '' && $value !== '') {
                $pairs[$name] = $value;
            }
        }

        ksort($pairs, SORT_STRING);

        return implode('|', array_map(
            static fn (string $name, string $value): string => "{$name}:{$value}",
            array_keys($pairs),
            array_values($pairs),
        ));
    }

    private static function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtolower($value, 'UTF-8');
    }
}
