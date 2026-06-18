<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FantasyTeam\CreateFantasyTeamRequest;
use App\Models\FantasyContest;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FantasyTeamController extends Controller
{
    // ── Create a fantasy team ──────────────────────────────────────────────
    // POST /api/fantasy-teams
    // Auth: required

    public function store(CreateFantasyTeamRequest $request): JsonResponse
    {
        $user          = $request->user();
        $contestId     = $request->contest_id;
        $playerIds     = $request->player_ids;
        $captainId     = $request->captain_id;
        $viceCaptainId = $request->vice_captain_id;

        DB::beginTransaction();

        try {
            // Lock the contest row for the duration of this transaction.
            // Any other request trying to join the same contest will wait
            // here until this transaction commits or rolls back.
            $contest = FantasyContest::lockForUpdate()->find($contestId);

            // Re-check slots now that we hold the lock — the value we read
            // here is guaranteed to be accurate, unlike the check in the
            // FormRequest which ran before the transaction.
            if (! $contest->hasSlots()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'This contest is now full.',
                ], 409);
            }

            // Re-check deadline inside the lock too — contest may have
            // just passed its deadline between validation and now.
            if ($contest->isDeadlinePassed()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'The deadline for this contest has just passed.',
                ], 409);
            }

            // Create the fantasy team
            $fantasyTeam = FantasyTeam::create([
                'user_id'      => $user->id,
                'contest_id'   => $contestId,
                'team_name'    => $request->team_name,
                'total_points' => 0,
                'rank'         => null,
            ]);

            // Insert all 11 players
            $rows = collect($playerIds)->map(fn ($playerId) => [
                'fantasy_team_id' => $fantasyTeam->id,
                'player_id'       => $playerId,
                'is_captain'      => $playerId == $captainId,
                'is_vice_captain' => $playerId == $viceCaptainId,
                'base_points'     => 0,
                'points'          => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ])->toArray();

            FantasyTeamPlayer::insert($rows);

            // Safe to increment now — we hold the lock so no other
            // request can read or write total_teams until we commit.
            $contest->increment('total_teams');

            DB::commit();

            // Load full team to return
            $fantasyTeam->load(['players.team', 'contest']);

            return response()->json([
                'message' => 'Fantasy team created successfully.',
                'team'    => $this->formatTeam($fantasyTeam),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create fantasy team. Please try again.',
            ], 500);
        }
    }

    // ── View my team for a contest ─────────────────────────────────────────
    // GET /api/contests/{contestId}/my-team
    // Auth: required

    public function myTeam(Request $request, int $contestId): JsonResponse
    {
        $fantasyTeam = FantasyTeam::with(['players.team', 'contest'])
            ->where('contest_id', $contestId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $fantasyTeam) {
            return response()->json([
                'message' => 'You have not entered this contest yet.',
                'team'    => null,
            ], 404);
        }

        return response()->json([
            'team' => $this->formatTeam($fantasyTeam),
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatTeam(FantasyTeam $team): array
    {
        $players = $team->players->map(function ($player) {
            return [
                'id'              => $player->id,
                'name'            => $player->name,
                'role'            => $player->role,
                'photo_url'       => $player->photo_url,
                'team_id'         => $player->team_id,
                'team_name'       => $player->team->name ?? null,
                'price'           => $player->price,
                'is_captain'      => (bool) $player->pivot->is_captain,
                'is_vice_captain' => (bool) $player->pivot->is_vice_captain,
                'base_points'     => $player->pivot->base_points,
                'points'          => $player->pivot->points,
            ];
        });

        return [
            'id'           => $team->id,
            'team_name'    => $team->team_name,
            'total_points' => $team->total_points,
            'total_price'  => round($players->sum('price'), 1),
            'budget'       => 100,
            'rank'         => $team->rank,
            'contest_id'   => $team->contest_id,
            'captain'      => $players->firstWhere('is_captain', true),
            'vice_captain' => $players->firstWhere('is_vice_captain', true),
            'players'      => $players->values(),
        ];
    }
}