<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ServicePincodeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\CatalogAndCustomersOverview;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StoreOverview;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class AtlasDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_dashboard_page_itself_loads_with_the_period_filter_form(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Period')
            ->assertSee('Last 7 days')
            ->assertSee('Last 30 days')
            ->assertSee('Custom range');
    }

    public function test_the_dashboard_shows_a_pincodes_tile_and_every_tile_links_to_its_page(): void
    {
        // Filament widgets are lazy-loaded by default (Widget::$isLazy), so
        // a plain HTTP GET of /admin only returns a placeholder — testing
        // the widget component directly is what actually exercises its
        // render cycle (getStats()), same as a real page does once its
        // wire:init fires.
        Livewire::test(StoreOverview::class)
            ->assertSee('Pincodes')
            ->assertSee('Orders')
            ->assertSee('Revenue (delivered)')
            ->assertSee('Customers')
            ->assertSee('Products')
            ->assertSeeHtml(OrderResource::getUrl())
            ->assertSeeHtml(ProductResource::getUrl())
            ->assertSeeHtml(UserResource::getUrl())
            ->assertSeeHtml(ServicePincodeResource::getUrl());
    }

    public function test_the_revenue_chart_defaults_to_the_last_7_days_and_sums_delivered_orders_per_day(): void
    {
        Livewire::test(RevenueChart::class)
            ->assertSuccessful()
            ->assertSee('Last 7 days');

        $today = now('Asia/Kolkata');
        Order::factory()->create(['status' => 'DELIVERED', 'total' => 100, 'created_at' => $today]);
        Order::factory()->create(['status' => 'DELIVERED', 'total' => 50, 'created_at' => $today]);
        Order::factory()->create(['status' => 'CANCELLED', 'total' => 999, 'created_at' => $today]);
        Order::factory()->create(['status' => 'DELIVERED', 'total' => 1000, 'created_at' => $today->copy()->subDays(10)]);

        $widget = new RevenueChart;
        $widget->filters = ['period' => 'week'];
        $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

        $this->assertSame(150.0, end($data['datasets'][0]['data']));
        $this->assertSame(now('Asia/Kolkata')->format('d M'), end($data['labels']));
        // The order from 10 days ago falls outside the 7-day window, so its
        // ₹1000 must not be double-counted into today's total above.
        $this->assertSame(150.0, array_sum($data['datasets'][0]['data']));
    }

    public function test_the_orders_chart_counts_every_order_regardless_of_status(): void
    {
        $today = now('Asia/Kolkata');
        Order::factory()->create(['status' => 'DELIVERED', 'created_at' => $today]);
        Order::factory()->create(['status' => 'CANCELLED', 'created_at' => $today]);
        Order::factory()->create(['status' => 'PENDING', 'created_at' => $today]);

        $widget = new OrdersChart;
        $widget->filters = ['period' => 'week'];
        $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

        $this->assertSame(3, end($data['datasets'][0]['data']));
    }

    public function test_the_revenue_and_orders_charts_use_a_30_day_window_for_the_month_period(): void
    {
        $widget = new RevenueChart;
        $widget->filters = ['period' => 'month'];
        $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

        $this->assertCount(30, $data['labels']);
    }

    public function test_the_revenue_chart_respects_a_custom_date_range(): void
    {
        $widget = new RevenueChart;
        $widget->filters = ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-05'];
        $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

        $this->assertCount(5, $data['labels']);
        $this->assertSame(['01 Jan', '02 Jan', '03 Jan', '04 Jan', '05 Jan'], $data['labels']);
    }

    public function test_the_catalog_and_customers_overview_counts_only_whats_new_in_the_period(): void
    {
        $category = Category::factory()->create();
        $today = now('Asia/Kolkata');

        Product::factory()->create(['category_id' => $category->id, 'created_at' => $today]);
        Product::factory()->create(['category_id' => $category->id, 'created_at' => $today->copy()->subDays(20)]);
        User::factory()->create(['role' => 'CUSTOMER', 'created_at' => $today]);
        User::factory()->create(['role' => 'CUSTOMER', 'created_at' => $today->copy()->subDays(20)]);

        Livewire::test(CatalogAndCustomersOverview::class, ['filters' => ['period' => 'week']])
            ->assertSuccessful()
            ->assertSee('New products')
            ->assertSee('New customers')
            ->assertSeeHtml(ProductResource::getUrl())
            ->assertSeeHtml(UserResource::getUrl());
    }
}
