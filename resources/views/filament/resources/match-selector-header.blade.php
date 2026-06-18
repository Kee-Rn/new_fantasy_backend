{{--
    Shared match-selector header partial.
    Used by ListMatchPlayers, ListPlayerPerformances, ListBallByBalls.

    Expects:
      $title       — section heading e.g. "Select Match"
      $description — helper text below heading
--}}

<x-filament::section>
    <x-slot name="heading">{{ $title }}</x-slot>
    <x-slot name="description">{{ $description }}</x-slot>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">

        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Match
            </label>
            <select
                wire:model.live="selectedMatchId"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
                <option value="">— Select a match to continue —</option>
                @foreach($this->getMatchOptions() as $id => $label)
                    <option value="{{ $id }}" @selected($this->selectedMatchId == $id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if($this->selectedMatchId)
            <div>
                <x-filament::button
                    wire:click="clearMatch"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-x-mark"
                >
                    Clear
                </x-filament::button>
            </div>
        @endif

    </div>

    @if($this->selectedMatchId && ($match = $this->getSelectedMatch()))
        <div class="mt-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4 text-success-500" />
            Showing results for
            <span class="font-semibold text-gray-900 dark:text-white">
                {{ $match->homeTeam?->name ?? '?' }} vs {{ $match->awayTeam?->name ?? '?' }}
            </span>
            @if($match->start_time)
                <span class="text-gray-400">&mdash; {{ $match->start_time->format('d M Y') }}</span>
            @endif
        </div>
    @endif

</x-filament::section>