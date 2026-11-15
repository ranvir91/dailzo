<?php

return [

    /**
     * Category slugs (not names or ids, so a category rename doesn't silently
     * drop out) for which the invoice calculates GST on the product's MRP
     * (order_items.mrp) rather than the actual charged amount
     * (order_items.price) — e.g. tobacco, where several Indian states/cess
     * rules tax on declared MRP regardless of any discount given at sale.
     *
     * Only affects how InvoiceService splits tax on the printed invoice — it
     * does NOT change checkout: the customer is still charged
     * discounted_price as normal, and the invoice's "Total" column for these
     * lines still reflects what was actually charged. Only the Taxable
     * Value/CGST/SGST/IGST figures for these specific lines are computed
     * against the higher MRP base, so (unlike every other line) they won't
     * sum back up to the Total — that's expected for this category of item.
     *
     * Comma-separated in .env, e.g. SPECIAL_TAX_CATEGORIES=tobacco,pan-masala
     */
    'special_tax_categories' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SPECIAL_TAX_CATEGORIES', 'tobacco'))
    ))),

];
