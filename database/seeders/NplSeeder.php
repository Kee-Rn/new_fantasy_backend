<?php

namespace Database\Seeders;

use App\Models\Player;
use Illuminate\Database\Seeder;

class NplSeeder extends Seeder
{
    public function run(): void
    {
        // ── Karnali Yaks (team_id: 1) ──────────────────────────────────────

        $karnaliPlayers = [
            ['name' => 'Deepak Dumre',        'role' => 'WK'],
            ['name' => 'Arjun Gharti',         'role' => 'WK'],
            ['name' => 'Jaykishan Kolsawala',  'role' => 'WK'],
            ['name' => 'Max O\'Dowd',          'role' => 'BAT'],
            ['name' => 'Priyank Panchal',      'role' => 'BAT'],
            ['name' => 'Shikhar Dhawan',       'role' => 'BAT'],
            ['name' => 'Yuvraj Khatri',        'role' => 'BAT'],
            ['name' => 'Najibullah Zadran',    'role' => 'BAT'],
            ['name' => 'Sompal Kami',          'role' => 'ALL'],
            ['name' => 'Will Bosisto',         'role' => 'ALL'],
            ['name' => 'Gulshan Jha',          'role' => 'ALL'],
            ['name' => 'Dipendra Rawat',       'role' => 'ALL'],
            ['name' => 'Pawan Sarraf',         'role' => 'ALL'],
            ['name' => 'Bipin Sharma',         'role' => 'BOWL'],
            ['name' => 'Imran Sheikh',         'role' => 'BOWL'],
            ['name' => 'Unish Thakuri',        'role' => 'BOWL'],
            ['name' => 'Mark Watt',            'role' => 'BOWL'],
            ['name' => 'Nandan Yadav',         'role' => 'BOWL'],
        ];

        foreach ($karnaliPlayers as $player) {
            Player::updateOrCreate(
                ['name' => $player['name'], 'team_id' => 1],
                ['role' => $player['role'], 'is_active' => true]
            );
        }

        // ── Chitwan Rhinos (team_id: 2) ────────────────────────────────────

        $chitwanPlayers = [
            ['name' => 'Kushal Malla',         'role' => 'BAT'],
            ['name' => 'Dawid Malan',          'role' => 'BAT'],
            ['name' => 'Bipin Rawal',          'role' => 'BAT'],
            ['name' => 'Deepak Bohara',        'role' => 'BAT'],
            ['name' => 'Saugat Dhakal',        'role' => 'BAT'],
            ['name' => 'Kamal Singh Airee',    'role' => 'ALL'],
            ['name' => 'Ravi Bopara',          'role' => 'ALL'],
            ['name' => 'Saif Zaib',            'role' => 'ALL'],
            ['name' => 'Alpesh Ramjani',       'role' => 'ALL'],
            ['name' => 'Amar Singh Routela',   'role' => 'ALL'],
            ['name' => 'Rijan Dhakal',         'role' => 'ALL'],
            ['name' => 'Dev Khanal',           'role' => 'ALL'],
            ['name' => 'Sohail Tanveer',       'role' => 'BOWL'],
            ['name' => 'Arjun Saud',           'role' => 'BOWL'],
            ['name' => 'Bipin Acharya',        'role' => 'BOWL'],
            ['name' => 'Ranjeet Kumar',        'role' => 'BOWL'],
            ['name' => 'Gautam KC',            'role' => 'BOWL'],
        ];

        foreach ($chitwanPlayers as $player) {
            Player::updateOrCreate(
                ['name' => $player['name'], 'team_id' => 2],
                ['role' => $player['role'], 'is_active' => true]
            );
        }

        $this->command->info('✓ Karnali Yaks — ' . count($karnaliPlayers) . ' players seeded');
        $this->command->info('✓ Chitwan Rhinos — ' . count($chitwanPlayers) . ' players seeded');
    }
}