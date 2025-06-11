<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\DestroyRoleRequest;
use App\Http\Requests\Admin\Role\GetPermissionsRequest;
use App\Http\Requests\Admin\Role\IndexRoleRequest;
use App\Http\Requests\Admin\Role\ShowRoleRequest;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        // Get user count for each role
        $roles = $roles->map(function ($role) {
            $userCount = User::role($role->name)->count();
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'user_count' => $userCount,
                'is_protected' => $role->is_protected,
            ];
        });

        return response()->json([
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $validated['name']]);

            if (!empty($validated['permissions'])) {
                $permissions = Permission::whereIn('name', $validated['permissions'])->get();
                $role->syncPermissions($permissions);
            }

            DB::commit();

            return response()->json([
                'message' => 'Role created successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                    'user_count' => 0,
                    'is_protected' => $role->is_protected,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified role.
     */
    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        $role->load('permissions');
        $userCount = User::role($role->name)->count();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'user_count' => $userCount,
                'is_protected' => $role->is_protected,
            ],
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $role->update(['name' => $validated['name']]);

            if (isset($validated['permissions'])) {
                $permissions = Permission::whereIn('name', $validated['permissions'])->get();
                $role->syncPermissions($permissions);
            }

            DB::commit();

            $userCount = User::role($role->name)->count();

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                    'user_count' => $userCount,
                    'is_protected' => $role->is_protected,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy(DestroyRoleRequest $request, Role $role): JsonResponse
    {
        // Check if role is in use
        $userCount = User::role($role->name)->count();
        if ($userCount > 0) {
            return response()->json([
                'message' => 'Cannot delete role that is assigned to users',
                'user_count' => $userCount,
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get all available permissions.
     */
    public function getPermissions(GetPermissionsRequest $request): JsonResponse
    {
        $permissions = Permission::all(['id', 'name']);

        // Group permissions by module
        $groupedPermissions = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $module = $parts[0] ?? 'Other';

            if (!isset($groupedPermissions[$module])) {
                $groupedPermissions[$module] = [];
            }

            $groupedPermissions[$module][] = [
                'id' => $permission->id,
                'name' => $permission->name,
            ];
        }

        return response()->json([
            'permissions' => $groupedPermissions,
        ]);
    }
}
