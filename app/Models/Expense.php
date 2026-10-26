<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property numeric-string|float $amount
 * @property string|null $description
 * @property Carbon|string $expense_date
 */
class Expense extends Model
{
    protected $fillable = ['user_id', 'category', 'amount', 'description', 'expense_date'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }
}
