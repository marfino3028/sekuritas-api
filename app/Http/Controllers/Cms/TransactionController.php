<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CMS TransactionController — daftar transaksi seluruh nasabah (read-only).
 *
 * Query params: status, type, date_from, date_to, search (order/nama/email), user_id, per_page.
 * Status `completed` di UI CMS dipetakan ke status `settled` di database.
 */
class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with(['user:id,name,email', 'fund:id,fund_code,name'])->latest();

        if ($request->filled('status')) {
            $status = $request->status === 'completed' ? Transaction::STATUS_SETTLED : $request->status;
            $query->where('status', $status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('order_number', 'like', "%{$keyword}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$keyword}%")
                      ->orWhere('email', 'like', "%{$keyword}%"));
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => collect($page->items())->map(fn (Transaction $t) => [
                'id'             => $t->id,
                'ref_number'     => $t->order_number,
                'user_id'        => $t->user_id,
                'user_name'      => $t->user?->name,
                'user_email'     => $t->user?->email,
                'product_name'   => $t->fund?->name,
                'fund_code'      => $t->fund?->fund_code,
                'type'           => $t->type,
                'amount'         => $t->amount,
                'units'          => $t->units,
                'nav_price'      => $t->nav_price,
                'status'         => $t->status === Transaction::STATUS_SETTLED ? 'completed' : $t->status,
                'payment_method' => $t->payment_method,
                'created_at'     => $t->created_at,
            ]),
            'meta'    => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
            'summary' => [
                'pending'     => Transaction::whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_PAID, Transaction::STATUS_PROCESSING])->count(),
                'completed'   => Transaction::where('status', Transaction::STATUS_SETTLED)->count(),
                'failed'      => Transaction::where('status', Transaction::STATUS_FAILED)->count(),
                'totalVolume' => (float) Transaction::where('status', Transaction::STATUS_SETTLED)->sum('amount'),
            ],
        ]);
    }
}
