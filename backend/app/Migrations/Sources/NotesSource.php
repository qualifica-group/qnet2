<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notes\Mentions\MentionParser;
use App\RichText\RichText;
use App\RichText\RichTextDom;
use App\Services\Notes\NoteBodyProcessor;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * `notes` migration source (spec 0311): the notes, calls and appointments of
 * every legacy module in one pass, written directly as `notes` rows without
 * activity log or mention notifications. The legacy sends the replies already
 * flattened onto their root and the body composed (D-2/D-3); here the `old_id`
 * lookups (subject, author, root note, mentioned users) and the qnet-2 rich
 * text rules (sanitizing, inline images as attachments of the note, D-4).
 */
class NotesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    /**
     * Contract subject types with a notes thread in qnet-2 (D-5): `registry`,
     * `purchase_request` and `referent` fail until their module gets one.
     *
     * @var array<string, class-string<Model>>
     */
    private const array NOTABLE_TYPES = [
        'work_order' => WorkOrder::class,
        'opportunity' => Opportunity::class,
        'task' => Task::class,
    ];

    private const string BODY_FIELD = 'body';

    public function __construct(
        ExternalApiClient $client,
        private readonly NoteBodyProcessor $bodyProcessor,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'notes';
    }

    public function label(): string
    {
        return 'Notes';
    }

    public function endpoint(): string
    {
        return 'notes';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
            MigrationDependency::required('work-orders'),
            MigrationDependency::optional('opportunities'),
            MigrationDependency::optional('tasks'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'subject_type', 'label' => 'Subject type', 'type' => 'string'],
            ['id' => 'subject_id', 'label' => 'Subject (external id)', 'type' => 'number'],
            ['id' => 'parent_id', 'label' => 'Root note (external id)', 'type' => 'number'],
            ['id' => 'kind', 'label' => 'Kind', 'type' => 'string'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'string'],
            ['id' => 'author_user_id', 'label' => 'Author (external id)', 'type' => 'number'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
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
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Note::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: where the note goes (a reply follows its root) and who wrote it.
        $note = $this->locatedNote($record);
        $author = User::query()->findOrFail($this->requiredOldId(User::class, $record['author_user_id'] ?? null, 'Author'));
        $note->user_id = $author->id;

        // Step 2: legacy mentions onto the qnet-2 users, then the provisional
        // body (images need the note to exist before they can attach to it).
        $rawBody = $this->remapMentions($this->requiredText($record['body'] ?? null, self::BODY_FIELD), $warnings);
        $note->body = $this->bodyProcessor->sanitize($rawBody);
        $note->saveQuietly();

        // Step 3: inline images as attachments of the note, the final body and its mentions.
        $note->body = $this->bodyProcessor->process($rawBody, $note, $author, self::BODY_FIELD)->html;
        $note->mentionedUsers()->sync(MentionParser::extractIds($note->body));

        // Step 4: the legacy anchors.
        $this->finalizeLegacyRecord($note, $externalId, $record, [
            'edited_at' => $this->legacyTimestamp($record['edited_at'] ?? null),
        ]);

        return MigrationRowOutcome::created($warnings, $note);
    }

    /**
     * A new note on its migrated subject, or on the subject of its migrated
     * root note when it is a reply (D-2).
     *
     * @param  array<string, mixed>  $record
     *
     * @throws RuntimeException when the subject or the root note is not migrated
     */
    private function locatedNote(array $record): Note
    {
        $note = new Note;
        $parentRef = $record['parent_id'] ?? null;

        if ($parentRef !== null && $parentRef !== '') {
            $root = Note::query()->findOrFail($this->requiredOldId(Note::class, $parentRef, 'Root note'));
            $note->parent_id = $root->id;
            $note->notable_type = $root->notable_type;
            $note->notable_id = $root->notable_id;

            return $note;
        }

        $subjectType = (string) ($record['subject_type'] ?? '');
        $notableClass = self::NOTABLE_TYPES[$subjectType]
            ?? throw new RuntimeException("Notes on {$subjectType} are not supported yet; the record is not imported.");

        $note->notable()->associate($notableClass::query()->findOrFail(
            $this->requiredOldId($notableClass, $record['subject_id'] ?? null, "Subject {$subjectType}")
        ));

        return $note;
    }

    /**
     * Every mention node carries a legacy user id: it points to the migrated
     * user, or becomes its "@Name" text with a warning when not migrated (D-4).
     *
     * @param  array<int, string>  $warnings
     */
    private function remapMentions(string $html, array &$warnings): string
    {
        $body = RichTextDom::parse($html);

        foreach (iterator_to_array($body->getElementsByTagName(RichText::MENTION_ELEMENT)) as $span) {
            if (RichTextDom::attr($span, RichText::MENTION_ATTR_TYPE) !== RichText::MENTION_DATA_TYPE) {
                continue;
            }

            $legacyId = RichTextDom::attr($span, RichText::MENTION_ATTR_ID);
            $userId = $legacyId === '' ? null : $this->resolveOldId(User::class, $legacyId);

            if ($userId === null) {
                $warnings[] = "Mentioned user (legacy id {$legacyId}) not migrated; kept as text.";
                RichTextDom::unwrap($span);

                continue;
            }

            $span->setAttribute(RichText::MENTION_ATTR_ID, (string) $userId);
        }

        return RichTextDom::serializeInner($body);
    }
}
