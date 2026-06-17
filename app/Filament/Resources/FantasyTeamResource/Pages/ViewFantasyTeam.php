<?php

namespace App\Filament\Resources\FantasyTeamResource\Pages;

use App\Filament\Resources\FantasyTeamResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewFantasyTeam extends ViewRecord
{
    protected static string $resource = FantasyTeamResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Team')
                ->schema([
                    Infolists\Components\TextEntry::make('team_name')->label('Team name')->weight('bold'),
                    Infolists\Components\TextEntry::make('user.name')->label('User'),
                    Infolists\Components\TextEntry::make('contest.name')->label('Contest'),
                    Infolists\Components\TextEntry::make('total_points')->label('Total points'),
                    Infolists\Components\TextEntry::make('rank')
                        ->label('Rank')
                        ->formatStateUsing(fn ($state) => $state ? '#' . $state : '—'),
                ])
                ->columns(3),

            Infolists\Components\Section::make('Players')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('fantasyTeamPlayers')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('player.name')->label('Player'),
                            Infolists\Components\TextEntry::make('player.role')->label('Role')->badge(),
                            Infolists\Components\TextEntry::make('player.team.name')->label('Team')->placeholder('—'),
                            Infolists\Components\IconEntry::make('is_captain')->label('C')->boolean(),
                            Infolists\Components\IconEntry::make('is_vice_captain')->label('VC')->boolean(),
                            Infolists\Components\TextEntry::make('base_points')->label('Base pts'),
                            Infolists\Components\TextEntry::make('points')->label('Final pts')->weight('bold'),
                        ])
                        ->columns(7),
                ]),

        ]);
    }
}