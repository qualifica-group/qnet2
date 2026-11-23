<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns one raw Formalab class payload into the formalab_classes columns.
 * Empty strings become null. The hash covers only the mapped columns, so a
 * change in the learners list alone never counts as a class change.
 */
final class ClassPayloadMapper
{
    private const string ITALIAN_DATE = '#^(\d{2})/(\d{2})/(\d{4})$#';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null null when the payload has no usable id
     */
    public function map(array $payload): ?array
    {
        $editionId = (int) ($payload['id'] ?? 0);

        if ($editionId <= 0) {
            return null;
        }

        $code = trim((string) ($payload['codice'] ?? ''));
        $title = trim((string) data_get($payload, 'corso.title', ''));

        $columns = [
            'edition_id' => $editionId,
            'code' => $code,
            'title' => $title,
            'label' => $code.' - '.$title,
            'status' => $this->text(data_get($payload, 'stato.name')),
            'course_status' => $this->text(data_get($payload, 'stato_corso.name')),
            'start_date' => $this->date($payload['start_dt'] ?? null),
            'end_date' => $this->date($payload['end_dt'] ?? null),
            'total_hours' => $this->integer(data_get($payload, 'corso.total_hours')),
            'exam_date' => $this->date($payload['data_esame'] ?? null),
            'ip_collection_date' => $this->date($payload['data_ip_incasso'] ?? null),
            'advance_decree' => $this->text($payload['anticipo_num_decreto_data'] ?? null),
            'advance_credited_at' => $this->date($payload['anticipo_data_accredito'] ?? null),
            'balance_decree' => $this->text($payload['saldo_num_decreto_data'] ?? null),
            'balance_credited_at' => $this->date($payload['saldo_data_accredito'] ?? null),
            'site_name' => $this->text(data_get($payload, 'sede_parte_teorica.denominazione')),
            'site_city' => $this->text(data_get($payload, 'sede_parte_teorica.city')),
            'site_code' => $this->text(data_get($payload, 'sede_parte_teorica.codice_sede')),
            'learners_count' => $this->integer($payload['num_discenti'] ?? null),
        ];

        return $columns + ['payload_hash' => hash('sha256', json_encode($columns, JSON_THROW_ON_ERROR))];
    }

    private function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? null : $text;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function date(mixed $value): ?string
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        try {
            $date = preg_match(self::ITALIAN_DATE, $text, $parts) === 1
                ? CarbonImmutable::createFromFormat('!d/m/Y', $text)
                : CarbonImmutable::parse($text);
        } catch (Throwable) {
            return null;
        }

        return $date === false ? null : $date->format('Y-m-d');
    }
}
