<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $summary = DB::table('purchases')->selectRaw('COUNT(*) as purchase_count, COALESCE(SUM(amount_cents), 0) as purchase_total_cents, COALESCE(AVG(amount_cents), 0) as purchase_average_cents, COALESCE(SUM(discount_cents), 0) as discount_total_cents, COALESCE(AVG(discount_cents), 0) as discount_average_cents')->first();
        $products = DB::table('purchase_items')->selectRaw('COALESCE(SUM(quantity * unit_price_cents), 0) as product_total_cents')->first();
        $customers = DB::table('purchases')->join('customers', 'customers.id', '=', 'purchases.customer_id')
            ->select('customers.id', 'customers.name')
            ->selectRaw('COUNT(*) as purchase_count, SUM(purchases.amount_cents) as total_cents, AVG(purchases.amount_cents) as average_cents')
            ->groupBy('customers.id', 'customers.name')->orderByDesc('total_cents')->get();
        $perPurchase = DB::table('purchase_items')->select('product_id', 'purchase_id')
            ->selectRaw('SUM(quantity * unit_price_cents) as total_cents')->groupBy('product_id', 'purchase_id');
        $byProduct = DB::query()->fromSub($perPurchase, 'item_totals')->join('products', 'products.id', '=', 'item_totals.product_id')
            ->select('products.id', 'products.name')
            ->selectRaw('COUNT(*) as purchase_count, SUM(item_totals.total_cents) as total_cents, AVG(item_totals.total_cents) as average_cents')
            ->groupBy('products.id', 'products.name')->orderByDesc('total_cents')->get();
        $summary->product_total_cents = $products->product_total_cents;
        $summary->product_average_cents = $summary->purchase_count ? $products->product_total_cents / $summary->purchase_count : 0;

        return response()->json(['summary' => $summary, 'customers' => $customers, 'products' => $byProduct]);
    }
}
