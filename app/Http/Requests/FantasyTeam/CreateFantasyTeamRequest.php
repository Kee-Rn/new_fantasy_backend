<?php

namespace App\Http\Requests\FantasyTeam;

use App\Models\FantasyContest;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateFantasyTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contest_id'      => ['required', 'integer', 'exists:fantasy_contests,id'],
            'team_name'       => ['required', 'string', 'max:100'],
            'player_ids'      => ['required', 'array', 'size:11'],
            'player_ids.*'    => ['integer', 'exists:players,id', 'distinct'],
            'captain_id'      => ['required', 'integer', 'exists:players,id'],
            'vice_captain_id' => ['required', 'integer', 'exists:players,id', 'different:captain_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'player_ids.size'           => 'You must select exactly 11 players.',
            'player_ids.*.distinct'     => 'Duplicate players are not allowed.',
            'captain_id.required'       => 'You must select a captain.',
            'vice_captain_id.required'  => 'You must select a vice-captain.',
            'vice_captain_id.different' => 'Captain and vice-captain must be different players.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $contestId     = $this->contest_id;
            $playerIds     = $this->player_ids;
            $captainId     = $this->captain_id;
            $viceCaptainId = $this->vice_captain_id;

            // ── 1. Contest must be open ────────────────────────────────────

            $contest = FantasyContest::find($contestId);

            if ($contest->isDeadlinePassed()) {
                $validator->errors()->add('contest_id', 'The deadline for this contest has passed.');
                return;
            }

            if (! $contest->hasSlots()) {
                $validator->errors()->add('contest_id', 'This contest is full.');
                return;
            }

            if (! in_array($contest->status, ['upcoming', 'active'])) {
                $validator->errors()->add('contest_id', 'This contest is not open for entries.');
                return;
            }

            // ── 2. User hasn't already entered this contest ────────────────

            $alreadyEntered = \App\Models\FantasyTeam::where('user_id', $this->user()->id)
                ->where('contest_id', $contestId)
                ->exists();

            if ($alreadyEntered) {
                $validator->errors()->add('contest_id', 'You have already entered this contest.');
                return;
            }

            // ── 3. All players must belong to one of the two match teams ───

            $match = GameMatch::findOrFail($contest->match_id);

            $validPlayerIds = Player::whereIn('id', $playerIds)
                ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $invalidPlayers = array_diff($playerIds, $validPlayerIds);

            if (! empty($invalidPlayers)) {
                $validator->errors()->add('player_ids', 'One or more selected players do not belong to either team in this match.');
                return;
            }

            // ── 4. Captain and VC must be in the selected 11 ──────────────

            if (! in_array($captainId, $playerIds)) {
                $validator->errors()->add('captain_id', 'Captain must be one of your 11 selected players.');
            }

            if (! in_array($viceCaptainId, $playerIds)) {
                $validator->errors()->add('vice_captain_id', 'Vice-captain must be one of your 11 selected players.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // ── 5. Composition rules ───────────────────────────────────────

            $players = Player::with('team')
                ->whereIn('id', $playerIds)
                ->get()
                ->keyBy('id');

            $roleCounts = $players->groupBy('role')->map->count();

            $wkCount   = $roleCounts->get('WK',   0);
            $batCount  = $roleCounts->get('BAT',  0);
            $allCount  = $roleCounts->get('ALL',  0);
            $bowlCount = $roleCounts->get('BOWL', 0);

            if ($wkCount < 1) {
                $validator->errors()->add('player_ids', 'You must select at least 1 wicketkeeper (WK).');
            }

            if ($bowlCount < 1) {
                $validator->errors()->add('player_ids', 'You must select at least 1 bowler (BOWL).');
            }

            if ($batCount < 1) {
                $validator->errors()->add('player_ids', 'You must select at least 1 batsman (BAT).');
            }

            if ($batCount > 7) {
                $validator->errors()->add('player_ids', 'You can select a maximum of 7 batsmen (BAT).');
            }

            // Max 6 from one team
            $teamCounts = $players->groupBy('team_id')->map->count();
            foreach ($teamCounts as $teamId => $count) {
                if ($count > 6) {
                    $validator->errors()->add('player_ids', 'You can select a maximum of 6 players from one team.');
                    break;
                }
            }

            // Min 1 from each team
            foreach ([$match->home_team_id, $match->away_team_id] as $teamId) {
                if (($teamCounts->get($teamId, 0)) === 0) {
                    $validator->errors()->add('player_ids', 'You must select at least 1 player from each team.');
                    break;
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // ── 6. Budget check — total price must not exceed 100 credits ──

            $totalPrice = $players->sum('price');

            if ($totalPrice > 100) {
                $validator->errors()->add(
                    'player_ids',
                    'Your team exceeds the 100 credit budget. Current total: ' . number_format($totalPrice, 1) . ' cr.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}