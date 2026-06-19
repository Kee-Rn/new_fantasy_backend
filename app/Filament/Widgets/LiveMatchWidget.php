<?php

namespace App\Filament\Widgets;

use App\Models\BallByBall;
use App\Models\FantasyContest;
use App\Models\GameMatch;
use Filament\Widgets\Widget;

class LiveMatchWidget extends Widget
{
    protected static ?int $sort = 2;

    // Poll every 15 seconds to stay fresh during live scoring
    protected static string $pollingInterval = '15s';

    protected static string $view = 'filament.widgets.live-match-widget';

    // Only show this widget when at least one match is live
    public static function canView(): bool
    {
        return GameMatch::where('status', 'live')->exists();
    }

    public function getData(): array
    {
        $liveMatches = GameMatch::where('status', 'live')
            ->with(['homeTeam', 'awayTeam', 'league'])
            ->get();

        return $liveMatches->map(function (GameMatch $match) {

            // Innings 1 score
            $inn1 = $match->liveScoreForInnings(1);

            // Innings 2 score (only if started)
            $lastBallInnings = BallByBall::where('match_id', $match->id)
                ->max('innings');
            $currentInnings = $lastBallInnings ?? 1;
            $inn2 = $currentInnings === 2 ? $match->liveScoreForInnings(2) : null;

            // Last 6 balls of current innings
            $recentBalls = BallByBall::where('match_id', $match->id)
                ->where('innings', $currentInnings)
                ->latest('id')
                ->limit(6)
                ->get()
                ->reverse()
                ->values();

            // Total balls entered
            $totalBalls = BallByBall::where('match_id', $match->id)->count();

            // Contests for this match
            $contests = FantasyContest::where('match_id', $match->id)
                ->selectRaw("COUNT(*) as total, SUM(status = 'active') as active")
                ->first();

            // Teams batting this innings
            $battingTeam  = $match->battingTeamForInnings($currentInnings);
            $fieldingTeam = $match->fieldingTeamForInnings($currentInnings);

            return [
                'match'           => $match,
                'current_innings' => $currentInnings,
                'batting_team'    => $battingTeam,
                'fielding_team'   => $fieldingTeam,
                'inn1'            => $inn1,
                'inn2'            => $inn2,
                'recent_balls'    => $recentBalls,
                'total_balls'     => $totalBalls,
                'contests'        => $contests,
                'score_url'       => route('filament.admin.resources.ball-by-balls.score', []) . '?match_id=' . $match->id,
            ];
        })->toArray();
    }
}