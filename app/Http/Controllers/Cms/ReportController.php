<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Models\MutualFund;
use App\Models\NavHistory;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CMS ReportController — unduh laporan CSV (bisa dibuka di Excel).
 * Semua endpoint menerima ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD.
 */
class ReportController extends Controller
{
    public function transactions(Request $request): StreamedResponse
    {
        $rows = $this->range(Transaction::with(['user:id,name,email', 'fund:id,fund_code,name']), $request)
            ->orderBy('created_at')->get()
            ->map(fn ($t) => [
                $t->order_number, $t->created_at?->format('Y-m-d H:i'), $t->user?->name, $t->user?->email,
                $t->fund?->fund_code, $t->fund?->name, $t->type, $t->amount, $t->units, $t->nav_price,
                $t->status, $t->payment_method,
            ]);

        return $this->csv('laporan-transaksi', [
            'No. Order', 'Tanggal', 'Nasabah', 'Email', 'Kode Produk', 'Produk', 'Jenis', 'Nominal', 'Unit', 'NAB', 'Status', 'Pembayaran',
        ], $rows);
    }

    public function kyc(Request $request): StreamedResponse
    {
        $rows = $this->range(Kyc::with('user:id,name,email,sid_number'), $request, 'submitted_at')
            ->orderBy('submitted_at')->get()
            ->map(fn ($k) => [
                $k->user?->name, $k->user?->email, $k->nik, $k->status,
                optional($k->submitted_at)->format('Y-m-d H:i'), optional($k->reviewed_at)->format('Y-m-d H:i'),
                $k->user?->sid_number, $k->rejected_reason,
            ]);

        return $this->csv('laporan-kyc', ['Nasabah', 'Email', 'NIK', 'Status', 'Diajukan', 'Direview', 'SID', 'Alasan Tolak'], $rows);
    }

    public function aum(Request $request): StreamedResponse
    {
        $rows = MutualFund::orderBy('name')->get()->map(function ($f) {
            $units = (float) Portfolio::where('fund_id', $f->id)->sum('total_units');
            return [$f->fund_code, $f->name, $f->fund_type_label, $f->nav_per_unit, $units, round($units * $f->nav_per_unit, 2),
                Portfolio::where('fund_id', $f->id)->where('total_units', '>', 0)->count()];
        });

        return $this->csv('laporan-aum', ['Kode', 'Produk', 'Jenis', 'NAB/Unit', 'Total Unit Nasabah', 'AUM Platform (Rp)', 'Jumlah Investor'], $rows);
    }

    public function users(Request $request): StreamedResponse
    {
        $rows = $this->range(User::where('role', User::ROLE_USER)->with('kyc:id,user_id,status'), $request)
            ->orderBy('created_at')->get()
            ->map(fn ($u) => [$u->name, $u->email, $u->phone, $u->status, $u->kyc?->status, $u->sid_status, $u->sid_number,
                $u->created_at?->format('Y-m-d H:i')]);

        return $this->csv('laporan-nasabah', ['Nama', 'Email', 'Telepon', 'Status Akun', 'Status KYC', 'Status SID', 'SID', 'Terdaftar'], $rows);
    }

    public function nav(Request $request): StreamedResponse
    {
        $rows = $this->range(NavHistory::with('fund:id,fund_code,name'), $request, 'nav_date')
            ->orderBy('nav_date')->orderBy('fund_id')->get()
            ->map(fn ($n) => [$n->nav_date instanceof \DateTimeInterface ? $n->nav_date->format('Y-m-d') : $n->nav_date,
                $n->fund?->fund_code, $n->fund?->name, $n->nav_per_unit, $n->nav_change, $n->nav_change_pct]);

        return $this->csv('laporan-nab', ['Tanggal', 'Kode', 'Produk', 'NAB/Unit', 'Perubahan', 'Perubahan (%)'], $rows);
    }

    private function range($query, Request $request, string $column = 'created_at')
    {
        if ($request->filled('date_from')) $query->whereDate($column, '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate($column, '<=', $request->date_to);
        return $query;
    }

    private function csv(string $name, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $header);
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, $name . '-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
