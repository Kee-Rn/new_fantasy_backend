<?php

namespace App\Filament\Resources\PlayerPerformanceResource\Pages;

use App\Filament\Resources\PlayerPerformanceResource;
use App\Services\Cricket\PointsCalculator;
use Filament\Resources\Pages\CreateRecord;

class CreatePlayerPerformance extends CreateRecord
{
    protected static string $resource = PlayerPerformanceResource::class;

    // Auto-calculate fantasy_points on creation
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove the non-persisted filter field if it slipped through
        unset($data['match_id_filter']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $points = app(PointsCalculator::class)->calculate($this->record);
        $this->record->update(['fantasy_points' => $points]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}