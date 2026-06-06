<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    /**
     * Daftar semua event (dengan filter & pagination).
     * GET /api/cms/events
     */
    public function index(Request $request): JsonResponse
    {
        $events = Event::withCount('registrations')
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('investment_manager', 'like', "%{$s}%")
            )
            ->when($request->type, fn ($q, $t) => $q->where('event_type', $t))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $events->map(fn ($e) => [
                'id'                 => $e->id,
                'code'               => $e->code,
                'name'               => $e->name,
                'investment_manager' => $e->investment_manager,
                'event_type'         => $e->event_type,
                'location'           => $e->location,
                'registered_count'   => $e->registered_count,
                'registrations_count'=> $e->registrations_count,
                'reward_quota'       => $e->reward_quota,
                'max_participants'   => $e->max_participants,
                'is_active'          => $e->is_active,
                'is_full'            => $e->isFull(),
                'start_at'           => $e->start_at->format('d M Y H:i'),
                'end_at'             => $e->end_at->format('d M Y H:i'),
                'created_at'         => $e->created_at->format('d M Y'),
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'total'        => $events->total(),
            ],
        ]);
    }

    /**
     * Detail satu event beserta statistik lengkap.
     * GET /api/cms/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::withCount('registrations')->findOrFail($id);

        $rewardCount = EventRegistration::where('event_id', $id)
            ->where('is_reward_eligible', true)
            ->count();

        return response()->json([
            'data' => array_merge($event->toArray(), [
                'reward_eligible_count' => $rewardCount,
                'registrations_count'   => $event->registrations_count,
                'reward_slots_remaining'=> $event->rewardSlotsRemaining(),
                'is_ongoing'            => $event->isOngoing(),
                'is_full'               => $event->isFull(),
            ]),
        ]);
    }

    /**
     * Buat event baru.
     * POST /api/cms/events
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'code'               => ['nullable', 'string', 'max:50', 'unique:events,code', 'regex:/^[A-Z0-9_\-]+$/'],
            'description'        => ['nullable', 'string'],
            'investment_manager' => ['required', 'string', 'max:255'],
            'location'           => ['nullable', 'string', 'max:255'],
            'event_type'         => ['required', Rule::in(['booth', 'seminar', 'webinar', 'roadshow', 'other'])],
            'reward_quota'       => ['nullable', 'integer', 'min:1'],
            'reward_description' => ['nullable', 'string'],
            'max_participants'   => ['nullable', 'integer', 'min:1'],
            'start_at'           => ['required', 'date'],
            'end_at'             => ['required', 'date', 'after:start_at'],
            'is_active'          => ['boolean'],
        ]);

        // Auto-generate kode jika tidak diisi
        if (empty($validated['code'])) {
            $base = strtoupper(Str::slug($validated['investment_manager'], '-'));
            $year = date('Y');
            $seq  = str_pad(Event::count() + 1, 3, '0', STR_PAD_LEFT);
            $validated['code'] = "{$base}-{$year}-{$seq}";
        } else {
            $validated['code'] = strtoupper($validated['code']);
        }

        $validated['created_by'] = $request->user()->id;

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event berhasil dibuat.',
            'data'    => $event,
        ], 201);
    }

    /**
     * Update event.
     * PUT /api/cms/events/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'code'               => ['sometimes', 'string', 'max:50', Rule::unique('events', 'code')->ignore($id), 'regex:/^[A-Z0-9_\-]+$/'],
            'description'        => ['nullable', 'string'],
            'investment_manager' => ['sometimes', 'string', 'max:255'],
            'location'           => ['nullable', 'string', 'max:255'],
            'event_type'         => ['sometimes', Rule::in(['booth', 'seminar', 'webinar', 'roadshow', 'other'])],
            'reward_quota'       => ['nullable', 'integer', 'min:1'],
            'reward_description' => ['nullable', 'string'],
            'max_participants'   => ['nullable', 'integer', 'min:1'],
            'start_at'           => ['sometimes', 'date'],
            'end_at'             => ['sometimes', 'date', 'after:start_at'],
            'is_active'          => ['boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $event->update($validated);

        return response()->json([
            'message' => 'Event berhasil diperbarui.',
            'data'    => $event->fresh(),
        ]);
    }

    /**
     * Toggle aktif/nonaktif event.
     * PUT /api/cms/events/{id}/toggle
     */
    public function toggle(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->update(['is_active' => !$event->is_active]);

        $status = $event->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message'   => "Event berhasil {$status}.",
            'is_active' => $event->is_active,
        ]);
    }

    /**
     * Hapus event (hanya jika belum ada pendaftar).
     * DELETE /api/cms/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        if ($event->registered_count > 0) {
            return response()->json([
                'message' => 'Event tidak bisa dihapus karena sudah ada peserta yang mendaftar.',
            ], 422);
        }

        $event->delete();

        return response()->json(['message' => 'Event berhasil dihapus.']);
    }

    /**
     * Leaderboard lengkap — daftar peserta tercepat beserta detail.
     * Hanya untuk admin (nama & HP tidak disensor).
     *
     * GET /api/cms/events/{id}/leaderboard
     */
    public function leaderboard(int $id, Request $request): JsonResponse
    {
        $event = Event::findOrFail($id);
        $limit = min((int) $request->input('limit', 100), 1000);

        $registrations = EventRegistration::with(['user:id,name,phone,email,status,sid_status'])
            ->where('event_id', $id)
            ->orderBy('registration_rank')
            ->limit($limit)
            ->get();

        return response()->json([
            'event' => [
                'id'                 => $event->id,
                'name'               => $event->name,
                'code'               => $event->code,
                'investment_manager' => $event->investment_manager,
                'reward_quota'       => $event->reward_quota,
                'reward_description' => $event->reward_description,
                'registered_count'   => $event->registered_count,
            ],
            'leaderboard' => $registrations->map(fn ($r) => [
                'rank'               => $r->registration_rank,
                'user_id'            => $r->user->id,
                'name'               => $r->user->name,
                'phone'              => $r->user->phone,
                'email'              => $r->user->email,
                'kyc_status'         => $r->user->status,
                'sid_status'         => $r->user->sid_status,
                'is_reward_eligible' => $r->is_reward_eligible,
                'registered_at'      => $r->registered_at->format('d M Y H:i:s.u'),
                'note'               => $r->note,
            ]),
        ]);
    }

    /**
     * Export leaderboard ke CSV untuk admin.
     * GET /api/cms/events/{id}/export
     */
    public function export(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $event = Event::findOrFail($id);

        $registrations = EventRegistration::with('user')
            ->where('event_id', $id)
            ->orderBy('registration_rank')
            ->get();

        $filename = "leaderboard-{$event->code}-" . date('Ymd') . ".csv";

        return response()->streamDownload(function () use ($registrations, $event) {
            $handle = fopen('php://output', 'w');

            // Header CSV
            fputcsv($handle, [
                'Rank', 'Nama', 'No. HP', 'Email',
                'Waktu Daftar', 'Eligible Reward', 'Catatan'
            ]);

            foreach ($registrations as $r) {
                fputcsv($handle, [
                    $r->registration_rank,
                    $r->user->name,
                    $r->user->phone,
                    $r->user->email,
                    $r->registered_at->format('d/m/Y H:i:s'),
                    $r->is_reward_eligible ? 'Ya' : 'Tidak',
                    $r->note,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
