<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AssignRoleRequest;
use App\Http\Requests\Admin\User\DestroyUserRequest;
use App\Http\Requests\Admin\User\GetRolesRequest;
use App\Http\Requests\Admin\User\IndexUserRequest;
use App\Http\Requests\Admin\User\ShowUserRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\ToggleStatusRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(IndexUserRequest $request): JsonResponse
    {
        $query = User::with('roles');

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filter by status (email verification)
        if ($request->has('status')) {
            if ($request->status === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Sorting
        $sortBy = $request->sortBy ?? 'created_at';
        $orderBy = $request->orderBy ?? 'desc';
        $query->orderBy($sortBy, $orderBy);

        // Pagination
        $perPage = $request->per_page ?? 10;
        $users = $query->paginate($perPage);

        // Transform data to include role names
        $usersCollection = collect($users->items())->map(function ($user) {
            $user->role_names = $user->getRoleNames();
            return $user;
        });

        return response()->json([
            'users' => $usersCollection,
            'totalUsers' => $users->total(),
            'currentPage' => $users->currentPage(),
            'perPage' => $users->perPage(),
            'lastPage' => $users->lastPage(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create user
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => $request->has('verified') && $request->verified ? now() : null,
        ]);

        // Assign roles if provided
        if (!empty($validated['roles'])) {
            $user->assignRole($validated['roles']);
        } else {
            // Assign default role
            $user->assignRole('Student');
        }

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('roles'),
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(ShowUserRequest $request, User $user): JsonResponse
    {
        $user->load(['roles', 'payments', 'subscriptions']);
        $user->role_names = $user->getRoleNames();

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // Update user
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->email = $validated['email'];

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Update verification status
        if ($request->has('verified')) {
            $user->email_verified_at = $request->verified ? now() : null;
        }

        $user->save();

        // Update roles if provided
        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(DestroyUserRequest $request, User $user): JsonResponse
    {
        // Prevent deleting self
        if (Auth::id() === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Toggle user verification status.
     */
    public function toggleStatus(ToggleStatusRequest $request, User $user): JsonResponse
    {
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        $status = $user->email_verified_at ? 'verified' : 'unverified';

        return response()->json([
            'message' => "User status changed to {$status}",
            'user' => $user,
        ]);
    }

    /**
     * Assign role to a user.
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $user->syncRoles($validated['roles']);

        return response()->json([
            'message' => 'Roles assigned successfully',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Get all available roles.
     */
    public function getRoles(GetRolesRequest $request): JsonResponse
    {
        $roles = Role::all(['id', 'name']);

        return response()->json([
            'roles' => $roles,
        ]);
    }
}
