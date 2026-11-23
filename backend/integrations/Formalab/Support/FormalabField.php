<?php

declare(strict_types=1);

namespace Integrations\Formalab\Support;

use Integrations\Formalab\Models\FormalabClass;

/**
 * The fields of a Formalab class a category can map onto its work order
 * attributes (spec 0423 D-5). `label` ("code - title") is the key the work
 * order uses to find its edition.
 */
enum FormalabField: string
{
    case Label = 'label';
    case Code = 'code';
    case Title = 'title';
    case ClassStatus = 'status';
    case CourseStatus = 'course_status';
    case StartDate = 'start_date';
    case EndDate = 'end_date';
    case TotalHours = 'total_hours';
    case ExamDate = 'exam_date';
    case IpCollectionDate = 'ip_collection_date';
    case AdvanceDecree = 'advance_decree';
    case AdvanceCreditedAt = 'advance_credited_at';
    case BalanceDecree = 'balance_decree';
    case BalanceCreditedAt = 'balance_credited_at';
    case SiteName = 'site_name';
    case SiteCity = 'site_city';
    case SiteCode = 'site_code';
    case LearnersCount = 'learners_count';

    public const string TYPE_TEXT = 'text';

    public const string TYPE_DATE = 'date';

    public const string TYPE_NUMBER = 'number';

    /** Attribute types able to hold a value of each field type. */
    private const array COMPATIBLE_TYPES = [
        self::TYPE_TEXT => ['text', 'textarea'],
        self::TYPE_DATE => ['date', 'text', 'textarea'],
        self::TYPE_NUMBER => ['integer', 'decimal', 'text', 'textarea'],
    ];

    /** The attribute codes the first release wrote (spec 0422 D-7), now only suggested. */
    private const array SUGGESTED_CODES = [
        'label' => 'gol_corso',
        'course_status' => 'gol_stato_corso',
        'status' => 'gol_stato_classe',
        'start_date' => 'gol_data_inizio_corso',
        'end_date' => 'gol_data_fine_corso',
        'total_hours' => 'gol_ore_totali_corso',
        'exam_date' => 'gol_data_esame',
        'ip_collection_date' => 'gol_data_ip_incasso',
        'advance_decree' => 'gol_anticipo_decreto_numero_data',
        'advance_credited_at' => 'gol_anticipo_data_accredito',
        'balance_decree' => 'gol_saldo_decreto_numero_data',
        'balance_credited_at' => 'gol_saldo_data_accredito',
    ];

    public function type(): string
    {
        return match ($this) {
            self::StartDate, self::EndDate, self::ExamDate, self::IpCollectionDate,
            self::AdvanceCreditedAt, self::BalanceCreditedAt => self::TYPE_DATE,
            self::TotalHours, self::LearnersCount => self::TYPE_NUMBER,
            default => self::TYPE_TEXT,
        };
    }

    /**
     * @return list<string>
     */
    public function compatibleTypes(): array
    {
        return self::COMPATIBLE_TYPES[$this->type()];
    }

    public function isKey(): bool
    {
        return $this === self::Label;
    }

    public function suggestedAttributeCode(): ?string
    {
        return self::SUGGESTED_CODES[$this->value] ?? null;
    }

    /** The class value as the attribute stores it: dates as Y-m-d, '' / missing as null. */
    public function valueFrom(FormalabClass $class): mixed
    {
        $value = $class->getAttribute($this->value);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === '' ? null : $value;
    }
}
