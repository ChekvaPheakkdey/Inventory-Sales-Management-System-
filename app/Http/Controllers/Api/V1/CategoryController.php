<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;

class CategoryController extends CrudController
{
    protected string $model = Category::class;

    protected function rules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ];
    }
}
