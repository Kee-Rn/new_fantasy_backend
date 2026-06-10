<x-filament-panels::page>
<div class="space-y-4" x-data="{ tab: 'ball' }">

    {{-- ── TOP BAR: Match + Innings selector ──────────────────────── --}}
    <x-filament::section>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

            {{-- Match --}}
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Match</label>
                <select wire:model.live="match_id"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-primary-500">
                    <option value="">— Select match —</option>
                    @foreach($this->getMatchOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Innings --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Innings</label>
                <select wire:model.live="innings"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-primary-500">
                    <option value="1">1st Innings</option>
                    <option value="2">2nd Innings</option>
                </select>
            </div>

        </div>
    </x-filament::section>

    @if($match_id)

    {{-- ── LIVE SCORECARD ───────────────────────────────────────────── --}}
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-6">

            {{-- Score --}}
            <div class="text-center">
                <p class="text-4xl font-black text-gray-900 dark:text-white">
                    {{ $total_runs }}<span class="text-2xl text-gray-500">/{{ $total_wickets }}</span>
                </p>
                <p class="text-xs text-gray-500 mt-0.5">SCORE</p>
            </div>

            <div class="w-px h-12 bg-gray-200 dark:bg-gray-700"></div>

            {{-- Overs --}}
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-700 dark:text-gray-300">
                    {{ $current_over }}.{{ max(0, $current_ball - 1) }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">OVERS</p>
            </div>

            <div class="w-px h-12 bg-gray-200 dark:bg-gray-700"></div>

            {{-- Recent balls --}}
            <div class="flex-1">
                <p class="text-xs text-gray-500 mb-2">LAST 12 BALLS</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($this->getRecentBalls() as $ball)
                        @php
                            $label = $ball->is_wicket ? 'W'
                                : ($ball->extra_type ? strtoupper(substr($ball->extra_type,0,2)) . ($ball->extra_runs ? '+'.$ball->extra_runs : '')
                                : (string)$ball->runs_off_bat);
                            $color = $ball->is_wicket    ? 'bg-red-500 text-white'
                                : ($ball->is_six         ? 'bg-green-500 text-white'
                                : ($ball->is_four        ? 'bg-blue-500 text-white'
                                : ($ball->extra_type     ? 'bg-yellow-400 text-gray-900'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200')));
                        @endphp
                        <span title="{{ ($ball->over_number+1) }}.{{ $ball->ball_number }} — {{ $ball->batsman?->name }} off {{ $ball->bowler?->name }}"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold {{ $color }} cursor-default">
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
            </div>

        </div>
    </x-filament::section>

    {{-- ── STICKY PLAYERS: Batsman + Bowler ──────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">On-field Players <span class="text-sm font-normal text-gray-400">(stays selected between balls)</span></x-slot>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                    Batsman (striker) <span class="text-red-500">*</span>
                </label>
                <select wire:model.live="batsman_id"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-primary-500">
                    <option value="">— Select batsman —</option>
                    @foreach($this->getBatsmanOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                    Bowler <span class="text-red-500">*</span>
                </label>
                <select wire:model.live="bowler_id"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-primary-500">
                    <option value="">— Select bowler —</option>
                    @foreach($this->getBowlerOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

        </div>
    </x-filament::section>

    {{-- ── ENTRY MODE TABS ─────────────────────────────────────────── --}}
    <div>
        {{-- Tab bar --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700 mb-0">
            <button type="button"
                @click="tab = 'ball'"
                :class="tab === 'ball'
                    ? 'border-b-2 border-primary-600 text-primary-600 font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                class="px-6 py-3 text-sm transition">
                ⚡ Ball by Ball
            </button>
            <button type="button"
                @click="tab = 'over'"
                :class="tab === 'over'
                    ? 'border-b-2 border-primary-600 text-primary-600 font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                class="px-6 py-3 text-sm transition">
                📋 Over by Over
            </button>
        </div>

        {{-- ── BALL BY BALL TAB ──────────────────────────────────────── --}}
        <div x-show="tab === 'ball'" x-cloak>
        <x-filament::section>

            <x-slot name="heading">
                Ball Entry —
                <span class="text-primary-600 font-bold">Over {{ $current_over + 1 }}, Ball {{ $current_ball }}</span>
            </x-slot>

            <div class="space-y-5">

                {{-- Runs --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Runs off bat</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([0,1,2,3,4,5,6] as $run)
                        <button type="button" wire:click="$set('runs_off_bat', {{ $run }})"
                            class="w-12 h-12 rounded-full text-lg font-bold border-2 transition
                                {{ $runs_off_bat == $run
                                    ? ($run == 4 ? 'bg-blue-500 border-blue-500 text-white'
                                        : ($run == 6 ? 'bg-green-500 border-green-500 text-white'
                                        : 'bg-primary-600 border-primary-600 text-white'))
                                    : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:border-primary-400' }}">
                            {{ $run }}
                        </button>
                        @endforeach
                    </div>
                    @if($is_four) <p class="mt-1 text-xs text-blue-600 font-semibold">📍 FOUR</p> @endif
                    @if($is_six)  <p class="mt-1 text-xs text-green-600 font-semibold">🚀 SIX</p>  @endif
                </div>

                {{-- Extras --}}
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Extra type</label>
                        <select wire:model.live="extra_type"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                            <option value="">None</option>
                            <option value="wide">Wide</option>
                            <option value="no_ball">No Ball</option>
                            <option value="bye">Bye</option>
                            <option value="leg_bye">Leg Bye</option>
                            <option value="penalty">Penalty</option>
                        </select>
                    </div>
                    @if($extra_type)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Extra runs</label>
                        <input type="number" wire:model="extra_runs" min="0" max="10"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                    </div>
                    @endif
                </div>

                {{-- Wicket --}}
                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="is_wicket"
                            class="w-5 h-5 rounded text-red-600 focus:ring-red-500">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">🔴 Wicket fell</span>
                    </label>

                    @if($is_wicket)
                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Wicket type</label>
                            <select wire:model="wicket_type"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                                <option value="">— Select —</option>
                                <option value="bowled">Bowled</option>
                                <option value="lbw">LBW</option>
                                <option value="caught">Caught</option>
                                <option value="caught_and_bowled">Caught & Bowled</option>
                                <option value="run_out">Run Out</option>
                                <option value="stumped">Stumped</option>
                                <option value="hit_wicket">Hit Wicket</option>
                                <option value="retired_hurt">Retired Hurt</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Dismissed player</label>
                            <select wire:model="dismissed_player_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                                <option value="">— Select —</option>
                                @foreach($this->getAllPlayersOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if(!in_array($wicket_type, ['bowled', 'lbw', 'hit_wicket', 'retired_hurt', '']))
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Fielder (catcher / stumper)</label>
                            <select wire:model="fielder_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                                <option value="">— Select —</option>
                                @foreach($this->getAllPlayersOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                    </div>
                    @endif
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" wire:model="notes" placeholder="e.g. DRS review, Power play ends..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                </div>

                {{-- Save button --}}
                <div class="pt-2">
                    <button type="button" wire:click="saveBall" wire:loading.attr="disabled"
                        class="w-full md:w-auto px-8 py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white font-bold rounded-xl text-sm transition shadow-sm">
                        <span wire:loading.remove wire:target="saveBall">
                            ✅ Save Ball — {{ $current_over + 1 }}.{{ $current_ball }}
                        </span>
                        <span wire:loading wire:target="saveBall">Saving...</span>
                    </button>
                </div>

            </div>
        </x-filament::section>
        </div>

        {{-- ── OVER BY OVER TAB ───────────────────────────────────────── --}}
        <div x-show="tab === 'over'" x-cloak>
        <x-filament::section>

            <x-slot name="heading">
                Over Entry —
                <span class="text-primary-600 font-bold">Over {{ $current_over + 1 }}</span>
            </x-slot>

            <div class="space-y-4">

                @foreach($over_balls as $i => $ball)
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">

                    <p class="text-xs font-bold text-gray-500 uppercase mb-3">Ball {{ $i + 1 }}</p>

                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">

                        {{-- Batsman --}}
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs text-gray-500 mb-1">Batsman</label>
                            <select wire:model="over_balls.{{ $i }}.batsman_id"
                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-xs">
                                <option value="">— Select —</option>
                                @foreach($this->getBatsmanOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Bowler --}}
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs text-gray-500 mb-1">Bowler</label>
                            <select wire:model="over_balls.{{ $i }}.bowler_id"
                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-xs">
                                <option value="">— Select —</option>
                                @foreach($this->getBowlerOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Runs --}}
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Runs</label>
                            <select wire:model="over_balls.{{ $i }}.runs_off_bat"
                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-xs">
                                @foreach([0,1,2,3,4,5,6] as $r)
                                    <option value="{{ $r }}">{{ $r }}{{ $r==4?' (4)':($r==6?' (6)':'') }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Extra --}}
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Extra</label>
                            <select wire:model="over_balls.{{ $i }}.extra_type"
                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-xs">
                                <option value="">None</option>
                                <option value="wide">Wide</option>
                                <option value="no_ball">No Ball</option>
                                <option value="bye">Bye</option>
                                <option value="leg_bye">Leg Bye</option>
                            </select>
                        </div>

                        {{-- Wicket --}}
                        <div class="flex flex-col justify-center">
                            <label class="block text-xs text-gray-500 mb-1">Wicket</label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="over_balls.{{ $i }}.is_wicket"
                                    class="rounded text-red-600 focus:ring-red-500">
                                <span class="text-xs text-red-600 font-semibold">OUT</span>
                            </label>
                        </div>

                        {{-- Wicket type (conditional) --}}
                        @if(!empty($over_balls[$i]['is_wicket']))
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Wicket type</label>
                            <select wire:model="over_balls.{{ $i }}.wicket_type"
                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-xs">
                                <option value="">— Type —</option>
                                <option value="bowled">Bowled</option>
                                <option value="lbw">LBW</option>
                                <option value="caught">Caught</option>
                                <option value="caught_and_bowled">C&B</option>
                                <option value="run_out">Run Out</option>
                                <option value="stumped">Stumped</option>
                                <option value="hit_wicket">Hit Wicket</option>
                            </select>
                        </div>
                        @endif

                    </div>
                </div>
                @endforeach

                {{-- Save over --}}
                <div class="pt-2">
                    <button type="button" wire:click="saveOver" wire:loading.attr="disabled"
                        class="w-full md:w-auto px-8 py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white font-bold rounded-xl text-sm transition shadow-sm">
                        <span wire:loading.remove wire:target="saveOver">✅ Save Over {{ $current_over + 1 }}</span>
                        <span wire:loading wire:target="saveOver">Saving...</span>
                    </button>
                </div>

            </div>
        </x-filament::section>
        </div>

    </div>
    {{-- end entry tabs --}}

    @else
    <x-filament::section>
        <div class="text-center py-10 text-gray-400">
            <x-heroicon-o-play-circle class="w-16 h-16 mx-auto mb-3" />
            <p class="text-lg font-medium">Select a match above to start scoring</p>
        </div>
    </x-filament::section>
    @endif

</div>
</x-filament-panels::page>  