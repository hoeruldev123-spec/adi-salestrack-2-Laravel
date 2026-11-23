<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'website', 'status', 'is_active'])]
class MasterPrincipal extends Model
{
    use HasFactory;

    protected $table = 'principals';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MasterPrincipal $principal) {
            if ($principal->isDirty('is_active')) {
                $principal->status = $principal->is_active ? 'ACTIVE' : 'INACTIVE';
            } elseif ($principal->isDirty('status')) {
                $principal->is_active = $principal->status === 'ACTIVE';
            } elseif (! $principal->exists) {
                $principal->is_active = $principal->status !== 'INACTIVE';
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'principal_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'principal_id');
    }
}
