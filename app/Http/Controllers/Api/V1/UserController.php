<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return User::query()->with('roles:id,name')
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            }))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', $filters['is_active']))
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /** @throws Throwable */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => ['required', 'distinct', Rule::exists('roles', 'name')],
            'is_active' => 'sometimes|boolean',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create(Arr::only($data, ['name', 'email', 'password']));
            $user->forceFill(['is_active' => $data['is_active'] ?? true])->save();
            $user->roles()->sync(Role::query()->whereIn('name', $data['roles'])->pluck('id'));

            return $user;
        });

        return response()->json($user->load('roles:id,name'), 201);
    }

    public function show(int $id): User
    {
        return User::query()->with('roles:id,name')->findOrFail($id);
    }

    /** @throws Throwable */
    public function update(Request $request, int $id): User
    {
        $user = User::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'sometimes|required|string|min:8|confirmed',
            'roles' => 'sometimes|required|array|min:1',
            'roles.*' => ['required', 'distinct', Rule::exists('roles', 'name')],
            'is_active' => 'sometimes|boolean',
        ]);

        $rolesWereProvided = array_key_exists('roles', $data);
        $passwordChanged = array_key_exists('password', $data);
        $willBeInactive = array_key_exists('is_active', $data) && ! $data['is_active'];
        $willLoseAdmin = $rolesWereProvided && ! in_array('admin', $data['roles'], true) && $user->hasRole('admin');

        if ($user->id === $request->user()->id && ($willBeInactive || $willLoseAdmin)) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate or remove the admin role from your own account.']);
        }

        if ($willLoseAdmin && User::query()->whereHas('roles', fn (Builder $query) => $query->where('name', 'admin'))->count() <= 1) {
            throw ValidationException::withMessages(['roles' => 'At least one admin account must remain.']);
        }

        DB::transaction(function () use ($user, $data, $rolesWereProvided, $passwordChanged, $willBeInactive) {
            $user->fill(Arr::only($data, ['name', 'email', 'password']))->save();

            if (array_key_exists('is_active', $data)) {
                $user->forceFill(['is_active' => $data['is_active']])->save();
            }

            if ($rolesWereProvided) {
                $user->roles()->sync(Role::query()->whereIn('name', $data['roles'])->pluck('id'));
            }

            if ($passwordChanged || $willBeInactive) {
                $user->apiTokens()->delete();
            }
        });

        return $user->refresh()->load('roles:id,name');
    }
}
