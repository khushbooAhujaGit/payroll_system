<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['company_id', 'key', 'value', 'group'];

    public static function getValue(string $key, ?int $companyId = null): ?string
    {
        return static::where('key', $key)
                     ->where('company_id', $companyId)
                     ->value('value');
    }

    public static function setValue(string $key, string $value, ?int $companyId = null): void
    {
        static::updateOrCreate(['key' => $key, 'company_id' => $companyId], ['value' => $value]);
    }
}
