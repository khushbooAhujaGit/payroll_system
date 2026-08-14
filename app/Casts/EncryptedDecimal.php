<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

/**
 * Stores a decimal amount encrypted at rest (Crypt::encryptString), while
 * still behaving like a normal fixed-precision decimal in PHP — unlike
 * Laravel's built-in 'encrypted' cast, which only round-trips strings.
 *
 * Usage: protected $casts = ['amount' => EncryptedDecimal::class . ':2'];
 */
class EncryptedDecimal implements CastsAttributes
{
    public function __construct(protected int $decimals = 2)
    {
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) Crypt::decryptString($value), $this->decimals);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString(number_format((float) $value, $this->decimals, '.', ''));
    }
}
