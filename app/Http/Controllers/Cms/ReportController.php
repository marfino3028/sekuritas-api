<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Models\MutualFund;
use App\Models\NavHistory;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CMS ReportController — laporan transaksi, KYC, AUM, nasabah, dan NAB.
 *
 * Semua endpoint menerima filter ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD plus filter khusus:
 *   transactions: status, type, fund_id · kyc: status · users: kyc_status · nav/aum: fund_id
 * Format keluaran (?format=): xlsx (default, file Excel), csv, atau json (pratinjau di CMS).
 */
class ReportController extends Controller
{
    private const TRX_STATUS = [
        'pending' => 'Menunggu Bayar', 'paid' => 'Dibayar (siap S-INVEST)', 'processing' => 'Diproses',
        'settled' => 'Selesai', 'failed' => 'Gagal',
    ];
    private const TRX_TYPE = ['subscription' => 'Pembelian', 'redemption' => 'Penjualan', 'switching' => 'Pengalihan'];
    private const KYC_STATUS = ['pending' => 'Menunggu Review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
    private const SID_STATUS = ['not_generated' => 'Belum', 'processing' => 'Diproses', 'active' => 'Aktif'];
    private const USER_STATUS = ['pending' => 'Pending', 'active' => 'Aktif', 'suspended' => 'Suspend'];

    public function transactions(Request $request): Response
    {
        $query = $this->range(Transaction::with(['user:id,name,email', 'fund:id,fund_code,name']), $request);
        if ($request->filled('status')) $query->where('status', $request->status === 'completed' ? 'settled' : $request->status);
        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('fund_id')) $query->where('fund_id', $request->fund_id);

        $rows = $query->orderByDesc('created_at')->get()->map(fn ($t) => [
            $t->order_number, $t->created_at?->format('Y-m-d H:i'), $t->user?->name, $t->user?->email,
            $t->fund?->fund_code, $t->fund?->name, self::TRX_TYPE[$t->type] ?? $t->type,
            (float) $t->amount, (float) $t->fee_amount, $t->units !== null ? (float) $t->units : null,
            $t->nav_price !== null ? (float) $t->nav_price : null, self::TRX_STATUS[$t->status] ?? $t->status,
            strtoupper(str_replace('_', ' ', (string) $t->payment_method)),
        ]);

        return $this->output($request, 'laporan-transaksi', 'Transaksi', [
            'No. Order', 'Tanggal', 'Nasabah', 'Email', 'Kode Produk', 'Produk', 'Jenis', 'Nominal (Rp)', 'Biaya (Rp)',
            'Unit', 'NAB/Unit', 'Status', 'Metode Bayar',
        ], $rows, ['total_nominal' => $rows->sum(fn ($r) => $r[7])]);
    }

    public function kyc(Request $request): Response
    {
        $query = $this->range(Kyc::with('user:id,name,email,sid_number,ifua_number'), $request, 'submitted_at');
        if ($request->filled('status')) $query->where('status', $request->status);

        $rows = $query->orderByDesc('submitted_at')->get()->map(fn ($k) => [
            $k->user?->name, $k->user?->email, $k->nik, self::KYC_STATUS[$k->status] ?? $k->status,
            optional($k->submitted_at)->format('Y-m-d H:i'), optional($k->reviewed_at)->format('Y-m-d H:i'),
            $k->user?->sid_number, $k->user?->ifua_number, $k->rejected_reason,
        ]);

        return $this->output($request, 'laporan-kyc', 'KYC', [
            'Nasabah', 'Email', 'NIK', 'Status', 'Diajukan', 'Direview', 'SID', 'IFUA', 'Alasan Tolak',
        ], $rows);
    }

    public function aum(Request $request): Response
    {
        $funds = MutualFund::orderBy('name');
        if ($request->filled('fund_id')) $funds->where('id', $request->fund_id);

        $rows = $funds->get()->map(function ($f) {
            $units = (float) Portfolio::where('fund_id', $f->id)->sum('total_units');
            return [
                $f->fund_code, $f->name, $f->fund_type_label, (float) $f->nav_per_unit, round($units, 4),
                round($units * (float) $f->nav_per_unit, 2), (float) $f->total_aum,
                Portfolio::where('fund_id', $f->id)->where('total_units', '>', 0)->count(),
            ];
        });

        return $this->output($request, 'laporan-aum', 'AUM', [
            'Kode', 'Produk', 'Jenis', 'NAB/Unit', 'Total Unit Nasabah', 'AUM Platform (Rp)', 'AUM Total Reksa Dana (Rp)', 'Jumlah Investor',
        ], $rows, ['total_aum_platform' => $rows->sum(fn ($r) => $r[5])]);
    }

    public function users(Request $request): Response
    {
        $query = $this->range(User::where('role', User::ROLE_USER)->with('kyc:id,user_id,status'), $request);
        if ($request->filled('kyc_status')) {
            $request->kyc_status === 'none'
                ? $query->doesntHave('kyc')
                : $query->whereHas('kyc', fn ($q) => $q->where('status', $request->kyc_status));
        }

        $rows = $query->orderByDesc('created_at')->get()->map(fn ($u) => [
            $u->name, $u->email, $u->phone, self::USER_STATUS[$u->status] ?? $u->status,
            self::KYC_STATUS[$u->kyc?->status ?? ''] ?? 'Belum KYC', self::SID_STATUS[$u->sid_status ?? ''] ?? $u->sid_status,
            $u->sid_number, $u->ifua_number, (float) $u->portfolios()->sum('current_value'), $u->created_at?->format('Y-m-d H:i'),
        ]);

        return $this->output($request, 'laporan-nasabah', 'Nasabah', [
            'Nama', 'Email', 'Telepon', 'Status Akun', 'Status KYC', 'Status SID', 'SID', 'IFUA', 'Nilai Portofolio (Rp)', 'Terdaftar',
        ], $rows);
    }

    public function nav(Request $request): Response
    {
        $query = $this->range(NavHistory::with('fund:id,fund_code,name'), $request, 'nav_date');
        if ($request->filled('fund_id')) $query->where('fund_id', $request->fund_id);

        $rows = $query->orderByDesc('nav_date')->orderBy('fund_id')->get()->map(fn ($n) => [
            $n->nav_date instanceof \DateTimeInterface ? $n->nav_date->format('Y-m-d') : substr((string) $n->nav_date, 0, 10),
            $n->fund?->fund_code, $n->fund?->name, (float) $n->nav_per_unit, (float) $n->nav_change, (float) $n->nav_change_pct,
        ]);

        return $this->output($request, 'laporan-nab', 'NAB', ['Tanggal', 'Kode', 'Produk', 'NAB/Unit', 'Perubahan', 'Perubahan (%)'], $rows);
    }

    private function range($query, Request $request, string $column = 'created_at')
    {
        if ($request->filled('date_from')) $query->whereDate($column, '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate($column, '<=', $request->date_to);
        return $query;
    }

    /** json = pratinjau (maks. 100 baris), csv, atau xlsx (default). */
    private function output(Request $request, string $name, string $sheet, array $header, $rows, array $summary = []): Response
    {
        $file = $name . '-' . now()->format('Ymd');
        $format = $request->input('format', 'xlsx');

        if ($format === 'json') {
            return new JsonResponse([
                'success' => true,
                'headers' => $header,
                'rows'    => $rows->take(100)->values(),
                'total'   => $rows->count(),
                'summary' => $summary,
            ]);
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($header, $rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
                fputcsv($out, $header, ';');  // ';' = pemisah default Excel berbahasa Indonesia
                foreach ($rows as $row) fputcsv($out, $row, ';');
                fclose($out);
            }, "{$file}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $book = new Spreadsheet();
        $ws = $book->getActiveSheet()->setTitle($sheet);
        $ws->fromArray($header, null, 'A1');
        $ws->fromArray($rows->map(fn ($r) => array_values($r))->all(), null, 'A2', true);
        $last = $ws->getHighestColumn();
        $ws->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $ws->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B203A');
        foreach (range(1, count($header)) as $i) {
            $ws->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $ws->freezePane('A2');
        $ws->setAutoFilter("A1:{$last}" . max(1, $rows->count() + 1));

        return new StreamedResponse(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$file}.xlsx\"",
            'Cache-Control'       => 'no-store',
        ]);
    }
}
