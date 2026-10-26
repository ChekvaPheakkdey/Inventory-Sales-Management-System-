<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;

class CustomerController extends CrudController
{
    protected string $model = Customer::class;

    protected array $searchable = ['name', 'phone', 'email'];

    protected function rules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ];
    }
}
