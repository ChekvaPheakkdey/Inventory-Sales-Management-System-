<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Unit;

class UnitController extends CrudController
{
    protected string $model = Unit::class;

    protected function rules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20',
        ];
    }
}
