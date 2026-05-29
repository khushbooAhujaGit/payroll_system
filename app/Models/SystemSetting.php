<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class SystemSetting extends Model
{
    protected $fillable = ['entity_id','key','value','group'];

    public static function getValue(string $key, ?int $entityId = null): ?string
    {
        return static::where('key', $key)
                     ->where('entity_id', $entityId)
                     ->value('value');
    }
    public static function setValue(string $key, string $value, ?int $entityId = null): void
    {
        static::updateOrCreate(['key' => $key, 'entity_id' => $entityId], ['value' => $value]);
    }
}
