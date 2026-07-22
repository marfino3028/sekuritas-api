<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed pendaftaran event/promo agar leaderboard & benefit terisi.
 * Rank menentukan kelayakan reward (sesuai reward_quota tiap event).
 */
class EventRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        $events = Event::all();
        $nasabah = User::where('role', User::ROLE_USER)->orderBy('id')->get();

        if ($events->isEmpty() || $nasabah->isEmpty()) {
            $this->command->warn('EventRegistrationSeeder dilewati: event atau nasabah belum ada.');
            return;
        }

        $total = 0;
        foreach ($events as $event) {
            // Ambil sebagian nasabah acak sebagai peserta
            $participants = $nasabah->shuffle()->take(rand(4, min(8, $nasabah->count())));
            $rank = 1;
            $quota = (int) ($event->reward_quota ?? 3);

            foreach ($participants as $user) {
                $reg = EventRegistration::firstOrCreate(
                    ['event_id' => $event->id, 'user_id' => $user->id],
                    [
                        'registration_rank'  => $rank,
                        'is_reward_eligible' => $rank <= $quota,
                        'registered_at'      => Carbon::parse($event->start_at ?? now())->addMinutes($rank * 7),
                        'note'               => $rank <= $quota ? 'Berhak atas reward promo.' : null,
                    ]
                );
                if ($reg->wasRecentlyCreated) {
                    $rank++;
                    $total++;
                }
            }

            $event->update(['registered_count' => EventRegistration::where('event_id', $event->id)->count()]);
        }

        $this->command->info("Event registration berhasil di-seed ({$total} pendaftaran).");
    }
}
