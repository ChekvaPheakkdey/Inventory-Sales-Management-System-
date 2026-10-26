<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected array $searchable = ['name'];

    protected array $with = [];

    abstract protected function rules(?int $id = null): array;

    protected function beforeCreate(array $data, Request $r): array
    {
        return $data;
    }

    /** PostgreSQL LIKE is case-sensitive; ILIKE is not. */
    protected function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    protected function modelQuery(): Builder
    {
        $modelClass = $this->model;
        $model = new $modelClass;

        return $model->newQuery();
    }

    public function index(Request $r): LengthAwarePaginator
    {
        $q = $this->modelQuery()->with($this->with)->latest('id');

        if ($s = $r->query('search')) {
            $op = $this->likeOperator();
            $q->where(function (Builder $w) use ($s, $op) {
                foreach ($this->searchable as $col) {
                    $w->orWhere($col, $op, "%$s%");
                }
            });
        }

        return $q->paginate($r->integer('per_page', 15));
    }

    public function store(Request $r): JsonResponse
    {
        $data = $this->beforeCreate($r->validate($this->rules()), $r);

        return response()->json($this->modelQuery()->create($data)->load($this->with), 201);
    }

    public function show(int $id): Model
    {
        return $this->modelQuery()->with($this->with)->findOrFail($id);
    }

    public function update(Request $r, int $id): Model
    {
        $m = $this->modelQuery()->findOrFail($id);
        $rules = $this->rules($id);
        foreach ($rules as $field => $rule) {
            $rules[$field] = is_array($rule)
                ? array_merge(['sometimes'], $rule)
                : 'sometimes|'.$rule;
        }
        $m->update($r->validate($rules));

        return $m->refresh()->load($this->with);
    }

    public function destroy(int $id): JsonResponse|Response
    {
        try {
            $this->modelQuery()->findOrFail($id)->delete();
        } catch (QueryException $exception) {
            if (! in_array($exception->errorInfo[0] ?? null, ['23000', '23503'], true)) {
                throw $exception;
            }

            return response()->json([
                'message' => 'This record cannot be deleted because other records depend on it.',
            ], 409);
        }

        return response()->noContent();
    }
}
