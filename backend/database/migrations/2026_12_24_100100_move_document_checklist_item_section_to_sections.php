<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Spec 0427, D-4: the free text `section` of the items becomes rows of
| `document_checklist_sections`, ordered by the first item that uses each name
| (names are grouped ignoring case and surrounding spaces, as the app validation
| requires), then the column is dropped. Idempotent: it does nothing once the
| column is gone. down() rebuilds the text from the section names.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_checklist_items', 'section')) {
            return;
        }

        DB::transaction(function (): void {
            $this->moveSectionsToTable();
        });

        Schema::table('document_checklist_items', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_checklist_items', 'section')) {
            return;
        }

        Schema::table('document_checklist_items', function (Blueprint $table) {
            $table->string('section', 255)->nullable()->after('description');
        });

        DB::table('document_checklist_items')
            ->whereNotNull('section_id')
            ->orderBy('id')
            ->each(function (object $item): void {
                DB::table('document_checklist_items')->where('id', $item->id)->update([
                    'section' => DB::table('document_checklist_sections')->where('id', $item->section_id)->value('name'),
                ]);
            });
    }

    private function moveSectionsToTable(): void
    {
        $now = now();

        $itemsByChecklist = DB::table('document_checklist_items')
            ->whereNotNull('section')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'document_checklist_id', 'section'])
            ->groupBy('document_checklist_id');

        foreach ($itemsByChecklist as $checklistId => $items) {
            $sectionIds = [];

            foreach ($items as $item) {
                $name = trim((string) $item->section);

                if ($name === '') {
                    continue;
                }

                $key = mb_strtolower($name);
                $sectionIds[$key] ??= $this->firstOrCreateSection((int) $checklistId, $name, count($sectionIds), $now);

                DB::table('document_checklist_items')->where('id', $item->id)->update(['section_id' => $sectionIds[$key]]);
            }
        }
    }

    private function firstOrCreateSection(int $checklistId, string $name, int $position, mixed $now): int
    {
        $existing = DB::table('document_checklist_sections')
            ->where('document_checklist_id', $checklistId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');

        return $existing ?? DB::table('document_checklist_sections')->insertGetId([
            'document_checklist_id' => $checklistId,
            'name' => $name,
            'description' => null,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
