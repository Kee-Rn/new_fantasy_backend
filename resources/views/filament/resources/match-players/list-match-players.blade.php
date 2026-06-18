<x-filament-panels::page>
    <div class="space-y-6">

        @include('filament.resources.match-selector-header', [
            'title'       => 'Select Match',
            'description' => 'Choose a match to view and manage its squad.',
        ])

        @if($this->selectedMatchId)
            {{ $this->table }}
        @else
            <x-filament::section>
                <div class="flex flex-col items-center justify-center py-12 text-center text-gray-400 dark:text-gray-500">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="w-12 h-12 mb-3 opacity-40" />
                    <p class="text-sm">Select a match above to view its players.</p>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>