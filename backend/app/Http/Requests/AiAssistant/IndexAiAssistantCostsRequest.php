<?php

declare(strict_types=1);

namespace App\Http\Requests\AiAssistant;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/ai-assistant/costs (spec 0433): the period, inclusive at both ends. Default: from the
 * first of the month of `to` up to today, in the application timezone.
 */
class IndexAiAssistantCostsRequest extends FormRequest
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:'.self::DATE_FORMAT, 'before_or_equal:to', $this->maximumRange()],
            'to' => ['required', 'date_format:'.self::DATE_FORMAT, 'before_or_equal:today'],
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $this->validated('from'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $this->validated('to'));
    }

    protected function prepareForValidation(): void
    {
        $to = $this->input('to', now()->format(self::DATE_FORMAT));
        $from = $this->input('from');

        // A non-date value is left as it is: the format rule rejects it
        if ($from === null && is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $from = substr($to, 0, 8).'01';
        }

        $this->merge(['from' => $from, 'to' => $to]);
    }

    private function maximumRange(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = is_string($value) ? date_create_immutable($value) : false;
            $to = is_string($this->input('to')) ? date_create_immutable($this->input('to')) : false;

            if ($from !== false && $to !== false && $from->diff($to)->days >= (int) config('ai-assistant.costs.report_max_days')) {
                $fail(__('The period is too long.'));
            }
        };
    }
}
