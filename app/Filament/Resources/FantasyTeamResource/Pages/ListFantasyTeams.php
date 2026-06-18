<?php

namespace App\Filament\Resources\FantasyTeamResource\Pages;

use App\Filament\Resources\FantasyTeamResource;
use Filament\Resources\Pages\ListRecords;

class ListFantasyTeams extends ListRecords
{
    protected static string $resource = FantasyTeamResource::class;

    // No header actions — fantasy teams are created by users via the API,
    // not by admin. canCreate() is false on the resource.

    // No tabs — points and ranks are calculated automatically on every ball
    // entry, so filtering by ranked/unranked is not meaningful.
}