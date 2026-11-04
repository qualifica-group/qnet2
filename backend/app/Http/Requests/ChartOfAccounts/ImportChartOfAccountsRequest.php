<?php

namespace App\Http\Requests\ChartOfAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/chart-of-accounts/import (spec 0349, D-11): an xlsx/csv
 * file (extension AND content type) and the preview/apply switch (multipart
 * clients send text, so `true`/`false` are accepted next to `1`/`0`). Row-level
 * permissions on cost types are checked by the importer, per row.
 */
class ImportChartOfAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chart-accounts.import') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,csv,txt',
                'extensions:xlsx,csv',
                'max:'.(int) config('imports.max_file_kb'),
            ],
            'dry_run' => ['required', Rule::in(['1', '0', 'true', 'false'])],
        ];
    }

    /**
     * The upload moved to a temp file that keeps its (validated) extension, which
     * is how the spreadsheet reader tells xlsx from csv. The caller deletes it.
     */
    public function spreadsheetPath(): string
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file->move(sys_get_temp_dir(), uniqid('chart-import-').'.'.Str::lower($file->getClientOriginalExtension()))->getPathname();
    }

    public function isDryRun(): bool
    {
        return $this->boolean('dry_run');
    }
}
