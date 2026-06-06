<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    /**
     * Cari event berdasarkan kode (case-insensitive).
     * Public — tidak perlu token. Dipakai di halaman "Masukkan kode event".
     *
     * GET /api/events/{code}
     */
    public function findByCode(string $code): JsonResponse
    {
        $event = Event::where('code', strtoupper(trim($code)))
            ->active()
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatEvent($event),
        ]);
    }

    /**
     * Daftar semua event aktif (untuk halaman explore / landing).
     * Public.
     *
     * GET /api/events
     */
    public function index(Request $request): JsonResponse
    {
        $events = Event::active()
            ->when($request->type, fn ($q, $t) => $q->where('event_type', $t))
            ->orderByDesc('start_at')
            ->paginate(12);

        return response()->json([
            'data' => $events->map(fn ($e) => $this->formatEvent($e)),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'total'        => $events->total(),
            ],
        ]);
    }

    /**
     * User mendaftar ke event via kode.
     * Gunakan DB transaction + lock agar rank akurat meski banyak concurrent request.
     *
     * POST /api/events/register
     * Body: { code: "SCHRODERS2025", note: "..." }
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $user) {
            // Lock row event agar registered_count tidak race condition
            $event = Event::where('code', strtoupper(trim($request->code)))
                ->active()
                ->lockForUpdate()
                ->first();

            if (!$event) {
                return response()->json([
                    'message' => 'Kode event tidak ditemukan atau sudah tidak aktif.',
                ], 404);
            }

            // Cek apakah event masih dalam periode pendaftaran
            if (now()->isAfter($event->end_at)) {
                return response()->json([
                    'message' => 'Periode pendaftaran event ini sudah berakhir.',
                ], 422);
            }

            // Cek kuota maksimum peserta
            if ($event->isFull()) {
                return response()->json([
                    'message' => "Maaf, pendaftaran event ini sudah penuh ({$event->max_participants} peserta).",
                ], 422);
            }

            // Cek apakah user sudah pernah daftar
            $alreadyRegistered = EventRegistration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyRegistered) {
                $existing = EventRegistration::where('event_id', $event->id)
                    ->where('user_id', $user->id)
                    ->first();

                return response()->json([
                    'message'       => 'Kamu sudah terdaftar di event ini.',
                    'already_registered' => true,
                    'data'          => $this->formatRegistration($existing, $event),
                ], 200);
            }

            // Tentukan rank (registered_count + 1 setelah lock)
            $rank         = $event->registered_count + 1;
            $rewardEligible = $event->reward_quota ? ($rank <= $event->reward_quota) : false;

            // Catat pendaftaran
            $registration = EventRegistration::create([
                'event_id'           => $event->id,
                'user_id'            => $user->id,
                'registration_rank'  => $rank,
                'is_reward_eligible' => $rewardEligible,
                'registered_at'      => now(),
                'note'               => $request->note,
            ]);

            // Increment counter event
            $event->increment('registered_count');

            return response()->json([
                'message' => $rewardEligible
                    ? "Selamat! Kamu terdaftar sebagai peserta ke-{$rank} dan memenuhi syarat reward!"
                    : "Berhasil mendaftar! Kamu adalah peserta ke-{$rank} event ini.",
                'data'    => $this->formatRegistration($registration, $event),
            ], 201);
        });
    }

    /**
     * Leaderboard event — siapa yang daftar paling cepat.
     * Public — boleh dilihat semua orang agar ada efek FOMO.
     *
     * GET /api/events/{code}/leaderboard?limit=50
     */
    public function leaderboard(string $code, Request $request): JsonResponse
    {
        $event = Event::where('code', strtoupper($code))->firstOrFail();
        $limit = min((int) $request->input('limit', 50), 200);

        $registrations = EventRegistration::with('user')
            ->where('event_id', $event->id)
            ->orderBy('registration_rank')
            ->limit($limit)
            ->get();

        $myRank = null;
        if ($request->user()) {
            $myRegistration = EventRegistration::where('event_id', $event->id)
                ->where('user_id', $request->user()->id)
                ->first();
            $myRank = $myRegistration ? $myRegistration->registration_rank : null;
        }

        return response()->json([
            'event'   => [
                'name'             => $event->name,
                'investment_manager' => $event->investment_manager,
                'reward_quota'     => $event->reward_quota,
                'reward_description' => $event->reward_description,
                'registered_count' => $event->registered_count,
            ],
            'my_rank' => $myRank,
            'leaderboard' => $registrations->map(fn ($r) => [
                'rank'              => $r->registration_rank,
                'name'              => $this->maskName($r->user->name),
                'phone'             => $this->maskPhone($r->user->phone),
                'registered_at'     => $r->registered_at->format('d M Y H:i:s'),
                'is_reward_eligible'=> $r->is_reward_eligible,
                'is_podium'         => $r->isPodium(),
            ]),
        ]);
    }

    /**
     * Riwayat event yang pernah diikuti oleh user yang sedang login.
     *
     * GET /api/events/my-registrations
     */
    public function myRegistrations(Request $request): JsonResponse
    {
        $registrations = EventRegistration::with('event')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('registered_at')
            ->get();

        return response()->json([
            'data' => $registrations->map(fn ($r) => $this->formatRegistration($r, $r->event)),
        ]);
    }

    // ============================================
    // Private helpers
    // ============================================

    private function formatEvent(Event $event): array
    {
        return [
            'id'                   => $event->id,
            'code'                 => $event->code,
            'name'                 => $event->name,
            'description'          => $event->description,
            'investment_manager'   => $event->investment_manager,
            'location'             => $event->location,
            'event_type'           => $event->event_type,
            'event_type_label'     => $this->eventTypeLabel($event->event_type),
            'reward_quota'         => $event->reward_quota,
            'reward_description'   => $event->reward_description,
            'reward_slots_remaining' => $event->rewardSlotsRemaining(),
            'max_participants'     => $event->max_participants,
            'registered_count'     => $event->registered_count,
            'is_full'              => $event->isFull(),
            'is_ongoing'           => $event->isOngoing(),
            'is_reward_available'  => $event->isRewardStillAvailable(),
            'start_at'             => $event->start_at->format('d M Y H:i'),
            'end_at'               => $event->end_at->format('d M Y H:i'),
            'banner_url'           => $event->banner_path
                ? asset('storage/' . $event->banner_path)
                : null,
        ];
    }

    private function formatRegistration(EventRegistration $reg, Event $event): array
    {
        return [
            'event_name'         => $event->name,
            'event_code'         => $event->code,
            'investment_manager' => $event->investment_manager,
            'rank'               => $reg->registration_rank,
            'rank_label'         => "Peserta Ke-{$reg->registration_rank}",
            'is_reward_eligible' => $reg->is_reward_eligible,
            'reward_description' => $event->reward_description,
            'reward_quota'       => $event->reward_quota,
            'registered_at'      => $reg->registered_at->format('d M Y H:i:s'),
            'is_podium'          => $reg->isPodium(),
        ];
    }

    /** Sensor nama: "Budi Santoso" → "Bu** Sa*****" */
    private function maskName(string $name): string
    {
        $words = explode(' ', $name);
        return implode(' ', array_map(function ($word) {
            if (strlen($word) <= 2) return $word;
            return substr($word, 0, 2) . str_repeat('*', strlen($word) - 2);
        }, $words));
    }

    /** Sensor nomor HP: "081234567890" → "0812****7890" */
    private function maskPhone(?string $phone): string
    {
        if (!$phone) return '****';
        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    private function eventTypeLabel(string $type): string
    {
        return match ($type) {
            'booth'    => 'Booth / Pameran',
            'seminar'  => 'Seminar Offline',
            'webinar'  => 'Webinar Online',
            'roadshow' => 'Roadshow',
            default    => 'Lainnya',
        };
    }
}
