<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function store(Request $request, PurchaseService $service)
    {
        [$purchase, $created] = $service->store($request->all());

        return response()->json(['created' => $created, 'data' => $purchase], $created ? 201 : 200);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'customer' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'amount_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2', Rule::when($request->filled('amount_min'), ['gte:amount_min'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', Rule::in(['reference', 'customer', 'date', 'discount', 'amount'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $query = Purchase::with('items.product');
        if (isset($filters['reference']) && $filters['reference'] !== '') {
            $query->whereLike('reference', '%'.$filters['reference'].'%');
        }
        $customer = $filters['customer'] ?? '';
        $canViewPersonalData = $request->user()->can('view-personal-data');
        if ($customer !== '' && ! $canViewPersonalData) {
            $query->whereLike('customer_name', '%'.$customer.'%');
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) {
                $query->whereDate('purchased_at', $operator, $filters[$key]);
            }
        }
        foreach (['amount_min' => '>=', 'amount_max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where('amount_cents', $operator, (int) round((float) $filters[$key] * 100));
            }
        }

        $sortColumns = [
            'reference' => 'reference',
            'customer' => 'customer_name',
            'date' => 'purchased_at',
            'discount' => 'discount_cents',
            'amount' => 'amount_cents',
        ];
        $query->orderBy($sortColumns[$filters['sort_by'] ?? 'date'], $filters['sort_direction'] ?? 'desc')
            ->orderByDesc('id');
        $purchases = $customer !== '' && $canViewPersonalData
            ? $this->searchPersonalData($query, $customer, (int) ($filters['page'] ?? 1), $request)
            : $query->paginate(10)->withQueryString();
        if (! $canViewPersonalData) {
            $purchases->getCollection()->each->makeHidden(['customer_email', 'customer_cpf', 'notes']);
        }

        return $purchases;
    }

    /** @param  Builder<Purchase>  $query */
    private function searchPersonalData(Builder $query, string $term, int $page, Request $request): LengthAwarePaginator
    {
        $cpf = preg_match('/^[0-9.\-\s]+$/', $term) ? preg_replace('/\D/', '', $term) : '';
        $pattern = '/'.str_replace(['%', '_'], ['.*', '.'], preg_quote($term, '/')).'/isu';
        $query->select('purchases.*')->selectRaw('LOWER(customer_name) LIKE LOWER(?) as customer_name_match', ['%'.$term.'%']);
        $items = new Collection;
        $total = 0;
        foreach ($query->cursor() as $purchase) {
            $matches = $purchase->getAttribute('customer_name_match')
                || preg_match($pattern, $purchase->customer_email) === 1
                || ($cpf !== '' && str_contains($purchase->customer_cpf, $cpf));
            unset($purchase->customer_name_match);
            if ($matches) {
                if ($total >= ($page - 1) * 10 && $total < $page * 10) {
                    $items->push($purchase);
                }
                $total++;
            }
        }
        $items->load('items.product');

        return new LengthAwarePaginator($items, $total, 10, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
