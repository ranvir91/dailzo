<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_ui_is_reachable_at_the_root_docs_path(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Dailzo API', escape: false);
    }

    public function test_the_openapi_spec_lists_routes_from_every_api_surface(): void
    {
        $response = $this->getJson('/docs/openapi.json')->assertOk();

        $paths = array_keys($response->json('paths'));

        // One representative route from each surface: public storefront,
        // authenticated customer, superadmin, and the delivery-partner app.
        $this->assertContains('/products', $paths);
        $this->assertContains('/cart', $paths);
        $this->assertContains('/admin/dashboard', $paths);
        $this->assertContains('/partner/login', $paths);
    }

    public function test_the_default_scramble_docs_api_path_is_not_registered(): void
    {
        // /docs/api is the package's own default route, superseded by /docs
        // (see AppServiceProvider::register() and routes/web.php).
        $this->get('/docs/api')->assertNotFound();
    }
}
