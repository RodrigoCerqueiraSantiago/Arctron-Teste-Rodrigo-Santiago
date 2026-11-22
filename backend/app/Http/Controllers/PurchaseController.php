<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
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
        ]);
        $query = Purchase::with('items.product');
        if (isset($filters['reference']) && $filters['reference'] !== '') {
            $query->whereLike('reference', '%'.$filters['reference'].'%');
        }
        if (isset($filters['customer']) && $filters['customer'] !== '') {
            $term = $filters['customer'];
            $cpf = preg_match('/^[0-9.\-\s]+$/', $term) ? preg_replace('/\D/', '', $term) : '';
            $canViewPersonalData = $request->user()->can('view-personal-data');
            $query->where(function ($query) use ($term, $cpf, $canViewPersonalData) {
                $query->whereLike('customer_name', '%'.$term.'%');
                if ($canViewPersonalData) {
                    $query->orWhereLike('customer_email', '%'.$term.'%');
                }
                if ($canViewPersonalData && $cpf !== '') {
                    $query->orWhere('customer_cpf', 'like', '%'.$cpf.'%');
                }
            });
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

        $purchases = $query->orderByDesc('purchased_at')->orderByDesc('id')->paginate(10)->withQueryString();
        if (! $request->user()->can('view-personal-data')) {
            $purchases->getCollection()->each->makeHidden(['customer_email', 'customer_cpf', 'notes']);
        }

        return $purchases;
    }
}
