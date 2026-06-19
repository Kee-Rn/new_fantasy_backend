<?php

namespace App\Filament\Widgets;

use App\Models\FantasyContest;
use App\Models\FantasyTeam;
use App\Models\GameMatch;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $matchCounts = GameMatch::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'upcoming')  as upcoming,
                SUM(status = 'live')      as live,
                SUM(status = 'completed') as completed
            ")
            ->first();

        $contestCounts = FantasyContest::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'active')    as active,
                SUM(status = 'completed') as completed
            ")
            ->first();

        return [
            Stat::make('Registered Users', number_format(User::count()))
                ->description('Total accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Fantasy Teams', number_format(FantasyTeam::count()))
                ->description('Created by users')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('success'),

            Stat::make('Matches', number_format($matchCounts->total ?? 0))
                ->description(
                    ($matchCounts->live      ? $matchCounts->live      . ' live · ' : '') .
                    ($matchCounts->upcoming  ? $matchCounts->upcoming  . ' upcoming · ' : '') .
                    ($matchCounts->completed ? $matchCounts->completed . ' completed' : '')
                )
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($matchCounts->live ? 'danger' : 'gray'),

            Stat::make('Contests', number_format($contestCounts->total ?? 0))
                ->description(
                    ($contestCounts->active    ? $contestCounts->active    . ' active · ' : '') .
                    ($contestCounts->completed ? $contestCounts->completed . ' completed' : '')
                )
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),
        ];
    }
}