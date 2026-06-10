<x-filament-panels::page>

    <div class="space-y-6">

        {{-- ── Match & Team selectors ────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Select Match & Team</x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                {{-- Match --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Match <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model.live="match_id"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">— Select match —</option>
                        @foreach($this->getMatchOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Team --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Team <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model.live="team_id"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                        @if(!$match_id) disabled @endif
                    >
                        <option value="">— Select team —</option>
                        @foreach($this->getTeamOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>
        </x-filament::section>

        {{-- ── Player list ─────────────────────────────────────────────── --}}
        @if($team_id && $availablePlayers->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                Select Players
                <span class="ml-2 text-sm font-normal text-gray-500">({{ $availablePlayers->count() }} available)</span>
            </x-slot>

            {{-- Status to assign --}}
            <div class="mb-4 flex items-center gap-6">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign as:</span>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model="xi_status" value="playing_xi" class="text-primary-600">
                    <span class="text-sm text-green-700 dark:text-green-400 font-medium">Playing XI</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model="xi_status" value="bench" class="text-primary-600">
                    <span class="text-sm text-yellow-700 dark:text-yellow-400 font-medium">Bench</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model="xi_status" value="unconfirmed" class="text-primary-600">
                    <span class="text-sm text-gray-600 dark:text-gray-400 font-medium">Unconfirmed</span>
                </label>
            </div>

            {{-- Select all / deselect all --}}
            <div class="mb-3 flex gap-3">
                <button
                    type="button"
                    wire:click="$set('selected', {{ json_encode($availablePlayers->pluck('id')->toArray()) }})"
                    class="text-xs text-primary-600 hover:underline"
                >Select all</button>
                <span class="text-gray-400">|</span>
                <button
                    type="button"
                    wire:click="$set('selected', [])"
                    class="text-xs text-gray-500 hover:underline"
                >Deselect all</button>
                <span class="ml-auto text-xs text-gray-500">{{ count($selected) }} selected</span>
            </div>

            {{-- Player checkboxes --}}
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($availablePlayers as $player)
                <label class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition
                    {{ in_array($player->id, $selected) ? 'bg-primary-50 border-primary-400 dark:bg-primary-900/20' : '' }}">

                    <input
                        type="checkbox"
                        wire:model.live="selected"
                        value="{{ $player->id }}"
                        class="rounded text-primary-600 focus:ring-primary-500"
                    >

                    @if($player->photo_path)
                        <img
                            src="{{ Storage::url($player->photo_path) }}"
                            class="w-8 h-8 rounded-full object-cover"
                            alt="{{ $player->name }}"
                        >
                    @else
                        <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-500">
                            {{ strtoupper(substr($player->name, 0, 1)) }}
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $player->name }}</p>
                        <p class="text-xs {{ $this->getRoleColor($player->role) }} font-semibold">{{ $player->role }}</p>
                    </div>

                </label>
                @endforeach
            </div>

            {{-- Save button --}}
            <div class="mt-6 flex justify-end">
                <x-filament::button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    size="lg"
                    icon="heroicon-o-check"
                >
                    <span wire:loading.remove>Assign {{ count($selected) > 0 ? count($selected) . ' Player(s)' : 'Players' }}</span>
                    <span wire:loading>Saving...</span>
                </x-filament::button>
            </div>

        </x-filament::section>

        @elseif($team_id && $availablePlayers->isEmpty())
        <x-filament::section>
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-2 text-green-500" />
                <p class="font-medium">All players from this team have already been assigned to this match.</p>
                <p class="text-sm mt-1">Use the <a href="{{ MatchPlayerResource::getUrl('index') }}" class="text-primary-600 hover:underline">Match Players list</a> to update individual statuses.</p>
            </div>
        </x-filament::section>

        @elseif($match_id && !$team_id)
        <x-filament::section>
            <div class="text-center py-6 text-gray-400">
                <x-heroicon-o-arrow-up class="w-8 h-8 mx-auto mb-2" />
                <p>Select a team to see available players.</p>
            </div>
        </x-filament::section>
        @endif

    </div>

</x-filament-panels::page>