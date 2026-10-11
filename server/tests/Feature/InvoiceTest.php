<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageStoreSettings;
use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(User $user, Product $product, array $orderOverrides = []): Order
    {
        $address = Address::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'line1' => 'House 1',
            'city' => 'Faridabad',
            'state' => 'Haryana',
            'pincode' => '121002',
        ]);

        $order = Order::create(array_merge([
            'user_id' => $user->id,
            'status' => 'PENDING',
            'payment_method' => 'COD',
            'total' => (float) $product->price,
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'address_id' => $address->id,
        ], $orderOverrides));

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'tax_rate' => $product->category->tax_rate,
            'mrp' => $product->mrp ?? 0,
        ]);

        return $order->fresh();
    }

    public function test_customer_can_download_their_own_order_invoice(): void
    {
        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1200, 'mrp' => 1500]);
        $user = User::factory()->create();
        $order = $this->placeOrder($user, $product, ['total' => 1200]);

        $response = $this->actingAsUser($user)->get("/api/v1/orders/{$order->id}/invoice");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_customer_cannot_download_another_users_invoice(): void
    {
        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->placeOrder($owner, $product);

        $this->actingAsUser($intruder)
            ->getJson("/api/v1/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertJsonPath('message', 'Order not found')
            ->assertJsonPath('data', null);
    }

    public function test_admin_can_download_any_orders_invoice(): void
    {
        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $owner = User::factory()->create();
        $order = $this->placeOrder($owner, $product);

        $response = $this->actingAsUser(User::factory()->admin()->create())
            ->get("/api/v1/orders/{$order->id}/invoice");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_tax_inclusive_price_is_split_into_cgst_and_sgst_for_an_intra_state_order(): void
    {
        StoreSetting::current()->update(['business_state' => 'Haryana']);

        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1200, 'mrp' => 1500]);
        $user = User::factory()->create();
        $order = $this->placeOrder($user, $product, ['total' => 1200]);

        $data = app(InvoiceService::class)->buildData($order);

        $this->assertTrue($data['isIntraState']);
        $line = $data['lines'][0];
        $this->assertSame(1016.95, $line['taxableValue']);
        $this->assertSame(91.53, $line['cgst']);
        $this->assertSame(91.52, $line['sgst']);
        $this->assertSame(0.0, $line['igst']);
        $this->assertSame(300.0, $line['discount']);
        $this->assertSame(1200.0, $line['total']);
    }

    public function test_interstate_order_is_charged_igst_instead_of_cgst_and_sgst(): void
    {
        StoreSetting::current()->update(['business_state' => 'Haryana']);

        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1200]);
        $user = User::factory()->create();
        $order = $this->placeOrder($user, $product, ['total' => 1200]);
        $order->address->update(['state' => 'Delhi']);

        $data = app(InvoiceService::class)->buildData($order->fresh());

        $this->assertFalse($data['isIntraState']);
        $line = $data['lines'][0];
        $this->assertSame(0.0, $line['cgst']);
        $this->assertSame(0.0, $line['sgst']);
        $this->assertSame(183.05, $line['igst']);
    }

    public function test_delivery_charges_are_shown_as_their_own_taxed_line(): void
    {
        $category = Category::factory()->create(['tax_rate' => 0]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 100]);
        $user = User::factory()->create();
        $order = $this->placeOrder($user, $product, ['total' => 129, 'delivery_charges' => 29]);

        $data = app(InvoiceService::class)->buildData($order);

        $this->assertNotNull($data['deliveryLine']);
        $this->assertSame(29.0, $data['deliveryLine']['total']);
        $this->assertSame(129.0, $data['subtotal']);
        $this->assertSame(129.0, $data['grandTotal']);
    }

    public function test_coupon_discount_is_shown_as_a_summary_deduction(): void
    {
        $category = Category::factory()->create(['tax_rate' => 0]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 100]);
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);
        $order = $this->placeOrder($user, $product, [
            'total' => 90,
            'coupon_id' => $coupon->id,
            'discount_amount' => 10,
        ]);

        $data = app(InvoiceService::class)->buildData($order);

        $this->assertSame('SAVE10', $data['couponCode']);
        $this->assertSame(10.0, $data['discount']);
        $this->assertSame(90.0, $data['grandTotal']);
    }

    public function test_amount_in_words_matches_the_grand_total(): void
    {
        $service = app(InvoiceService::class);

        $this->assertSame('One Thousand Two Hundred Twenty Nine Rupees Only', $service->amountInWords(1229));
        $this->assertSame('Ninety Nine Rupees And Fifty Paise Only', $service->amountInWords(99.5));
        $this->assertSame('Zero Rupees Only', $service->amountInWords(0));
    }

    public function test_admin_can_save_the_invoice_company_details_in_atlas(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'business_name' => 'Dailzo Quick Commerce Pvt Ltd',
                'gstin' => '06AAAAA0000A1Z5',
                'business_address' => 'Plot 1, Sector 5, Faridabad',
                'business_state' => 'Haryana',
                'business_pincode' => '121002',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = StoreSetting::current()->fresh();
        $this->assertSame('Dailzo Quick Commerce Pvt Ltd', $settings->business_name);
        $this->assertSame('06AAAAA0000A1Z5', $settings->gstin);
        $this->assertSame('Haryana', $settings->business_state);
    }
}
