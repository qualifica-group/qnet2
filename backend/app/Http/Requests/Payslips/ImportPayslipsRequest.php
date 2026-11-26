<?php

namespace App\Http\Requests\Payslips;

use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/purchase-invoices/payslips/import (spec 0431, D-8, D-11):
 * a csv/txt/xlsx file (extension AND content type), the preview/apply switch
 * (multipart clients send text, so `true`/`false` are accepted next to `1`/`0`)
 * and the payment date of the installments.
 */
class ImportPayslipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('import', PurchaseInvoice::class) && $user->can('create', PurchaseInvoice::class);
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
            'payment_date' => ['required', 'date_format:Y-m-d'],
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

        return $file->move(sys_get_temp_dir(), uniqid('payslip-import-').'.'.Str::lower($file->getClientOriginalExtension()))->getPathname();
    }

    public function isDryRun(): bool
    {
        return $this->boolean('dry_run');
    }

    public function paymentDate(): string
    {
        return (string) $this->validated('payment_date');
    }
}
