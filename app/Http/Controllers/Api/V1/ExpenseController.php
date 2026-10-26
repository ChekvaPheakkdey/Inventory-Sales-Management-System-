<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends CrudController
{
    protected string $model = Expense::class;

    protected array $searchable = ['category', 'description'];

    protected function rules(?int $id = null): array
    {
        return [
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'expense_date' => 'required|date',
        ];
    }

    protected function beforeCreate(array $data, Request $r): array
    {
        return $data + ['user_id' => $r->user()->id];
    }
}
