<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Migrations\Realign\AbstractSourceRealignTarget;
use App\Migrations\Realign\Support\QuotePositionedPeople;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Model;

/** Opportunita' (spec 0375 D-11, fields of spec 0372 D-5). */
final class OpportunitiesTarget extends AbstractSourceRealignTarget
{
    private const array COLUMNS = ['name', 'start_date', 'expected_close_date', 'estimated_value', 'general_notes'];

    public function source(): string
    {
        return 'opportunities';
    }

    protected function modelClass(): string
    {
        return Opportunity::class;
    }

    public function label(Model $record): string
    {
        return (string) $record->getAttribute('name');
    }

    public function snapshot(Model $record): array
    {
        /** @var Opportunity $record */
        return [
            ...$this->columns($record, self::COLUMNS),
            'supervisor' => $this->personName($record->supervisor),
            'managers' => QuotePositionedPeople::describe($record->managers()),
            'product_categories' => $this->json($record->productLines()->orderBy('product_category_id')->pluck('product_category_id')->all()),
        ];
    }
}
