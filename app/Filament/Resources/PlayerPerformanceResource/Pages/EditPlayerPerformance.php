<?php

namespace App\Filament\Resources\PlayerPerformanceResource\Pages;

use App\Filament\Resources\PlayerPerformanceResource;
use App\Services\Cricket\PointsCalculator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlayerPerformance extends EditRecord
{
    protected static string $resource = PlayerPerformanceResource::class;

    // Auto-recalculate fantasy_points whenever stats are saved
    protected function afterSave(): void
    {
        $points = app(PointsCalculator::class)->calculate($this->record);
        $this->record->update(['fantasy_points' => $points]);
    }

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