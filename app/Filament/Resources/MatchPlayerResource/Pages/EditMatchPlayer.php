<?php

namespace App\Filament\Resources\MatchPlayerResource\Pages;

use App\Filament\Resources\MatchPlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMatchPlayer extends EditRecord
{
    protected static string $resource = MatchPlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}