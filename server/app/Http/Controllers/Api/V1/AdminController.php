<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success([
            'totalOrders' => Order::count(),
            'totalCustomers' => User::where('role', 'CUSTOMER')->count(),
            'pendingOrders' => Order::whereIn('status', ['PENDING', 'PAYMENT_PENDING', 'CONFIRMED', 'PROCESSING'])->count(),
        ], 'Admin dashboard data fetched successfully');
    }

    public function orders(): JsonResponse
    {
        return ApiResponse::success(
            Order::with('items.product')->orderByDesc('created_at')->get(),
            'Admin orders fetched successfully',
        );
    }
}
