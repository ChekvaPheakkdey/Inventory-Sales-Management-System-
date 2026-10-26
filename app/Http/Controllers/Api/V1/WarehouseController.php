<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Warehouse;

class WarehouseController extends CrudController
{
    protected string $model = Warehouse::class;

    protected function rules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
