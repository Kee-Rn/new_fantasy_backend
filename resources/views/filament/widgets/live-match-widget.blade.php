<x-filament-widgets::widget>
    <x-filament::section>

        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-danger-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-danger-500"></span>
                </span>
                <span>Live Now</span>
            </div>
        </x-slot>

        @php $matches = $this->getData(); @endphp

        @forelse($matches as $data)
            @php
                $match   = $data['match'];
                $inn1    = $data['inn1'];
                $inn2    = $data['inn2'];
                $recent  = $data['recent_balls'];
                $batting = $data['batting_team'];
                $fielding= $data['fielding_team'];
            @endphp

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4 {{ !$loop->first ? 'mt-4' : '' }}">

                {{-- Match header --}}
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $match->league?->name ?? 'Match' }} &middot; {{ $match->match_type }}
                        </p>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $match->homeTeam?->name ?? '?' }}
                            <span class="text-gray-400 font-normal text-sm mx-1">vs</span>
                            {{ $match->awayTeam?->name ?? '?' }}
                        </h3>
                    </div>
                    <a
                        href="{{ $data['score_url'] }}"
                        class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-success-500 hover:bg-success-600 text-white transition"
                    >
                        <x-filament::icon icon="heroicon-m-play-circle" class="w-3.5 h-3.5" />
                        Enter Scores
                    </a>
                </div>

                {{-- Scorecard --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-3">
                        <p class="text-xs text-gray-400 mb-1">1st Innings</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $inn1['runs'] }}/{{ $inn1['wickets'] }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $inn1['overs'] }} ov</p>
                    </div>

                    @if($inn2)
                        <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 px-4 py-3">
                            <p class="text-xs text-primary-400 mb-1">2nd Innings</p>
                            <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">
                                {{ $inn2['runs'] }}/{{ $inn2['wickets'] }}
                            </p>
                            <p class="text-xs text-primary-400">{{ $inn2['overs'] }} ov</p>
                        </div>
                    @else
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-3 flex items-center justify-center">
                            <p class="text-xs text-gray-400">2nd innings not started</p>
                        </div>
                    @endif

                </div>

                {{-- Current batting/fielding --}}
                @if($batting)
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-m-arrow-right" class="w-3.5 h-3.5 text-success-500" />
                        <span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ $batting->name }}</span>
                            batting
                            @if($fielding)
                                vs <span class="font-medium text-gray-700 dark:text-gray-200">{{ $fielding->name }}</span>
                            @endif
                        </span>
                    </div>
                @endif

                {{-- Last 6 balls — uses correct column names: runs_off_bat, extra_type --}}
                @if($recent->count())
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-2">Last {{ $recent->count() }} deliveries</p>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach($recent as $ball)
                                @php
                                    $isWide   = $ball->extra_type === 'wide';
                                    $isNoBall = $ball->extra_type === 'no_ball';

                                    $label = $ball->is_wicket ? 'W'
                                        : ($isWide              ? 'Wd'
                                        : ($isNoBall            ? 'Nb'
                                        : ($ball->is_six        ? '6'
                                        : ($ball->is_four       ? '4'
                                        : ($ball->runs_off_bat == 0 ? '•' : $ball->runs_off_bat)))));

                                    $color = $ball->is_wicket ? 'bg-danger-500 text-white'
                                        : ($ball->is_six      ? 'bg-success-500 text-white'
                                        : ($ball->is_four     ? 'bg-primary-500 text-white'
                                        : ($isWide || $isNoBall
                                                              ? 'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300'
                                        : ($ball->runs_off_bat == 0
                                                              ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'))));
                                @endphp
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold {{ $color }}">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Footer --}}
                <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500 pt-1 border-t border-gray-100 dark:border-gray-700">
                    <span>
                        <span class="font-medium text-gray-600 dark:text-gray-300">{{ $data['total_balls'] }}</span>
                        balls recorded
                    </span>
                    <span>
                        <span class="font-medium text-gray-600 dark:text-gray-300">{{ $data['contests']?->total ?? 0 }}</span>
                        contest(s) &middot;
                        <span class="font-medium text-success-600">{{ $data['contests']?->active ?? 0 }} active</span>
                    </span>
                </div>

            </div>

        @empty
            <p class="text-sm text-gray-400">No live matches at the moment.</p>
        @endforelse

    </x-filament::section>
</x-filament-widgets::widget>