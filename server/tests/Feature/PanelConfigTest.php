<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\AccountWidget;
use Tests\TestCase;

class PanelConfigTest extends TestCase
{
    public function test_both_panels_have_a_collapsible_sidebar(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->isSidebarCollapsibleOnDesktop());
        $this->assertTrue(Filament::getPanel('octa')->isSidebarCollapsibleOnDesktop());
    }

    public function test_both_panels_use_the_full_content_width(): void
    {
        $this->assertSame(MaxWidth::Full, Filament::getPanel('admin')->getMaxContentWidth());
        $this->assertSame(MaxWidth::Full, Filament::getPanel('octa')->getMaxContentWidth());
    }

    public function test_neither_dashboard_shows_the_account_widget(): void
    {
        // AccountWidget is Filament's built-in "Welcome, {name}" card with its
        // own logout link — removed from both dashboards since logout already
        // lives in the topbar profile menu.
        $this->assertNotContains(AccountWidget::class, Filament::getPanel('admin')->getWidgets());
        $this->assertNotContains(AccountWidget::class, Filament::getPanel('octa')->getWidgets());
    }
}
