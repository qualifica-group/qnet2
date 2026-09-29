<?php

namespace App\Migrations\Sources;

use App\DataObjects\EmailTemplates\CreateEmailTemplateData;
use App\Enums\EmailTemplateModule;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use App\Services\OutboundEmails\EmailHtmlSanitizer;
use RuntimeException;

/**
 * `email-templates` migration source (spec 0175, D-13): the legacy
 * `email_templates` table (id, title, body — no subject, no segnaposto, no
 * permission) becomes an `App\Models\EmailTemplate` with `name = subject =
 * title`, `module = work_orders` (D-10: the only module this version
 * ships), `body` sanitized through the SAME EmailHtmlSanitizer the live CRUD
 * uses (D-11). Phase 1 anchor (no dependency on any other source).
 *
 * `name`/`subject` are truncated to their column limits with a warning
 * (never a fatal row); a `name` collision within `module` (a row already
 * migrated with a DIFFERENT `old_id`, or a manually-created template) is
 * resolved by appending a numeric suffix, also warned — never a row error,
 * mirroring CostProductsSource::resolveCode()'s "one clashing value never
 * fails the row" philosophy. Idempotent on `old_id` (skipped).
 */
class EmailTemplatesSource extends AbstractMigrationSource
{
    private const int NAME_MAX = 191;

    private const int SUBJECT_MAX = 255;

    public function __construct(
        ExternalApiClient $client,
        private readonly EmailTemplateService $service,
        private readonly EmailHtmlSanitizer $sanitizer,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'email-templates';
    }

    public function label(): string
    {
        return 'Email templates';
    }

    public function endpoint(): string
    {
        return 'email-templates';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'string'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'title' => $record['title'] ?? null,
            'body' => $record['body'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(EmailTemplate::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $title = trim((string) ($record['title'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('title is required.');
        }

        $warnings = [];
        $module = EmailTemplateModule::WorkOrders;
        $name = $this->uniqueName($module, $this->truncated($title, self::NAME_MAX, 'name', $warnings), $warnings);
        $subject = $this->truncated($title, self::SUBJECT_MAX, 'subject', $warnings);
        $body = $this->sanitizedBody((string) ($record['body'] ?? ''), $warnings);

        $emailTemplate = $this->service->create(new CreateEmailTemplateData(
            name: $name,
            module: $module,
            subject: $subject,
            body: $body,
            description: null,
            isActive: true,
        ));

        $emailTemplate->old_id = (int) $externalId;
        $emailTemplate->save();

        return MigrationRowOutcome::created($warnings, $emailTemplate);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function truncated(string $value, int $max, string $field, array &$warnings): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $warnings[] = "{$field} truncated to {$max} characters (was ".mb_strlen($value).').';

        return mb_substr($value, 0, $max);
    }

    /**
     * A `name` unique within `module` (data_model: unique(module, name)). A
     * collision with an existing row (any `old_id`, including none) is
     * resolved by appending " (2)", " (3)", ... rather than failing the row
     * (D-13: "scegli la regola piu' sicura").
     *
     * @param  array<int, string>  $warnings
     */
    private function uniqueName(EmailTemplateModule $module, string $name, array &$warnings): string
    {
        if (! $this->nameExists($module, $name)) {
            return $name;
        }

        $original = $name;
        $suffix = 2;

        do {
            $suffixText = " ({$suffix})";
            $budget = self::NAME_MAX - mb_strlen($suffixText);
            $candidate = (mb_strlen($original) > $budget ? mb_substr($original, 0, $budget) : $original).$suffixText;
            $suffix++;
        } while ($this->nameExists($module, $candidate));

        $warnings[] = "name '{$original}' already used for module '{$module->value}'; renamed to '{$candidate}'.";

        return $candidate;
    }

    private function nameExists(EmailTemplateModule $module, string $name): bool
    {
        return EmailTemplate::query()->where('module', $module)->where('name', $name)->exists();
    }

    /**
     * Sanitizes $rawBody with the SAME EmailHtmlSanitizer the live "Modelli
     * email" CRUD uses (D-11), warning (never failing) when the sanitized
     * result differs from the original beyond mere whitespace — the legacy
     * body is TinyMCE HTML, likely to carry tables/styles/images the current
     * allow-list drops.
     *
     * @param  array<int, string>  $warnings
     */
    private function sanitizedBody(string $rawBody, array &$warnings): string
    {
        $sanitized = $this->sanitizer->sanitize($rawBody);

        if ($this->normalizedForComparison($sanitized) !== $this->normalizedForComparison($rawBody)) {
            $warnings[] = 'body sanitized: legacy markup outside the current allow-list (tables, styles, images, ...) was stripped.';
        }

        return $sanitized;
    }

    private function normalizedForComparison(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }
}
