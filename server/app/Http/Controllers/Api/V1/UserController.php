<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** Admin — list all users. */
    public function index(): JsonResponse
    {
        return ApiResponse::success(User::all(), 'Users fetched successfully');
    }

    /** The authenticated user's own profile. */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($request->user(), 'User fetched successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (! $this->canActOn($request, $id)) {
            return ApiResponse::success(null, 'User not found');
        }

        $user = User::find($id);

        return ApiResponse::success($user, $user ? 'User fetched successfully' : 'User not found');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (! $this->canActOn($request, $id)) {
            return ApiResponse::success(null, 'User not found');
        }

        $user = User::find($id);
        if (! $user) {
            return ApiResponse::success(null, 'User not found');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'string', 'filled'],
            'gender' => ['sometimes', 'nullable', 'string'],
            'role' => ['sometimes', Rule::in(['CUSTOMER', 'ADMIN'])],
        ]);

        foreach (['name', 'gender'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'] ?: null;
        }
        if (array_key_exists('phone', $data)) {
            $user->phone = trim($data['phone']);
        }
        // Only an admin may change a role; a non-admin self-update keeps its role.
        if (array_key_exists('role', $data) && $request->user()->isAdmin()) {
            $user->role = $data['role'];
        }

        $user->save();

        return ApiResponse::success($user, 'User updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($request->user()->id === $id) {
            return ApiResponse::success(null, 'You cannot delete your own account');
        }

        $user = User::find($id);
        if (! $user) {
            return ApiResponse::success(null, 'User not found');
        }

        $user->delete();

        return ApiResponse::success(null, 'User deleted successfully');
    }

    private function canActOn(Request $request, string $id): bool
    {
        return $request->user()->id === $id || $request->user()->isAdmin();
    }
}
