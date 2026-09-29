<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/**
 * Renders the sidebar brand mark for a panel: brand name text followed by
 * the Dailzo logo, e.g. "Atlas <logo>", sized up (2x the original icon/text
 * size) so it reads clearly in the header. Used as ->brandLogo() on both
 * AdminPanelProvider and OctaPanelProvider so the expanded-sidebar header
 * looks identical apart from the name. The collapsed-sidebar state (small
 * icon only — there isn't room for the full logo on the narrow rail) is
 * handled separately by the published sidebar view override at
 * resources/views/vendor/filament-panels/components/sidebar/index.blade.php.
 */
class BrandLogo
{
    public static function html(string $brandName): HtmlString
    {
        $logoUrl = e(asset('images/Dailzo_logo.png'));
        $brandName = e($brandName);

        return new HtmlString(
            <<<HTML
                <span class="flex items-center gap-2.5 font-bold leading-none tracking-tight text-gray-950 dark:text-white" style="font-size: 2.5rem;">
                    <span>{$brandName}</span>
                    <img src="{$logoUrl}" alt="" class="h-10 w-auto object-contain" />
                </span>
                HTML
        );
    }
}
