<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use Illuminate\Http\Request;
use Integrations\Formalab\Models\FormalabClass;

/**
 * A Formalab class as a select option: id = edition id, label "code - title",
 * subtitle = site, class status and dates (spec 0426 D-4).
 *
 * @mixin FormalabClass
 */
class ClassForSelectResource extends ForSelectResource
{
    private const string DATE_FORMAT = 'd/m/Y';

    private const string SEPARATOR = ' · ';

    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        $dates = implode(' - ', array_filter([
            $this->start_date?->format(self::DATE_FORMAT),
            $this->end_date?->format(self::DATE_FORMAT),
        ]));
        $subtitle = implode(self::SEPARATOR, array_filter([$this->site_name, $this->status, $dates]));

        return [
            'id' => $this->edition_id,
            'label' => $this->label,
            'subtitle' => $subtitle === '' ? null : $subtitle,
        ];
    }
}
