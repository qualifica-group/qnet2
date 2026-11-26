<?php

namespace App\Http\Requests\PayrollCosts;

use App\Models\PayrollCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/payroll-costs/import (spec 0430, D-1, D-6): a csv/txt/xlsx
 * file (extension AND content type) and the preview/apply switch (multipart
 * clients send text, so `true`/`false` are accepted next to `1`/`0`).
 */
class ImportPayrollCostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', PayrollCost::class) ?? false;
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
                'extensions:xlsx,csv,txt',
                'max:'.(int) config('imports.max_file_kb'),
            ],
            'dry_run' => ['required', Rule::in(['1', '0', 'true', 'false'])],
        ];
    }

    /**
     * The upload moved to a temp file that keeps its (validated) extension, which
     * is how the reader tells xlsx from text. The caller deletes it.
     */
    public function spreadsheetPath(): string
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file->move(sys_get_temp_dir(), uniqid('payroll-import-').'.'.Str::lower($file->getClientOriginalExtension()))->getPathname();
    }

    public function isDryRun(): bool
    {
        return $this->boolean('dry_run');
    }
}
