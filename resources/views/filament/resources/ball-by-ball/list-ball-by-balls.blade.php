<x-filament-panels::page>
    <div class="space-y-6">

        @include('filament.resources.match-selector-header', [
            'title'       => 'Select Match',
            'description' => 'Choose a match to view its ball-by-ball log or enter live scores.',
        ])

        @if($this->selectedMatchId)
            {{ $this->table }}
        @else
            <div class="flex flex-col items-center justify-center py-16 text-center text-gray-400 dark:text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                </svg>
                <p class="text-sm">Select a match above to view deliveries.</p>
            </div>
        @endif

    </div>
</x-filament-panels::page>