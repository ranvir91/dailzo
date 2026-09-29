<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    /**
     * Public — active, in-window coupons for the storefront. Not behind
     * auth:sanctum (guests browse coupons before logging in), but resolves
     * the caller via the sanctum guard directly when a token is present, so
     * each coupon can carry how many times *this* user has already used it
     * — the client needs that to grey out a coupon once per_user_limit is
     * hit; the API request that actually redeems the coupon still enforces
     * the limit itself regardless of what the client shows.
     */
    public function index(): JsonResponse
    {
        $coupons = Coupon::active()->orderByDesc('created_at')->get();

        $user = Auth::guard('sanctum')->user();
        $usageCounts = $user
            ? CouponUsage::where('user_id', $user->id)
                ->whereIn('coupon_id', $coupons->pluck('id'))
                ->selectRaw('coupon_id, count(*) as count')
                ->groupBy('coupon_id')
                ->pluck('count', 'coupon_id')
            : collect();

        $data = $coupons->map(fn (Coupon $coupon) => [
            ...$coupon->toArray(),
            'usedByUser' => (int) ($usageCounts[$coupon->id] ?? 0),
        ]);

        return ApiResponse::success($data, 'Coupons fetched successfully');
    }

    /** Admin — every non-deleted coupon. */
    public function adminIndex(): JsonResponse
    {
        return ApiResponse::success(
            Coupon::orderByDesc('created_at')->get(),
            'Coupons fetched successfully',
        );
    }

    public function show(string $code): JsonResponse
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        return ApiResponse::success($coupon, $coupon ? 'Coupon found' : 'Coupon not found');
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->all();
        $this->coupons->validate($input, creating: true);

        $coupon = Coupon::create($this->coupons->toColumns($input, creating: true));

        return ApiResponse::success($coupon, 'Coupon created successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return ApiResponse::success(null, 'Coupon not found');
        }

        $input = $request->all();
        $this->coupons->validate($input, creating: false);
        $coupon->update($this->coupons->toColumns($input, creating: false));

        return ApiResponse::success($coupon->fresh(), 'Coupon updated successfully');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return ApiResponse::success(null, 'Coupon not found');
        }

        $data = $request->validate(['isActive' => ['required', 'boolean']]);
        $coupon->update(['is_active' => $data['isActive']]);

        return ApiResponse::success(
            $coupon->fresh(),
            $data['isActive'] ? 'Coupon activated successfully' : 'Coupon deactivated successfully',
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return ApiResponse::success(null, 'Coupon not found');
        }

        $coupon->delete();

        return ApiResponse::success(null, 'Coupon deleted successfully');
    }
}
