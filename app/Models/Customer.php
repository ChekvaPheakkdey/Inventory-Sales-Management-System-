<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property-read Collection<int, Sale> $sales
 */
class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
