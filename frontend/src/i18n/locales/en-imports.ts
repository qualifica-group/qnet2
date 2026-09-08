/**
 * Localized strings for the generic per-table CSV import feature (spec 0012):
 * the upload step, the validating/processing progress view, the
 * awaiting_confirmation preview and the error messages surfaced by the
 * frozen import contract.
 *
 * Extracted into its own module to keep `en.ts` within the engineering size
 * limits (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is
 * unchanged.
 */
export const imports = {
  /** Toolbar action label (opens the import dialog, gated by `{resource}.import`). */
  action: 'Import',
  title: 'Import data',
  subtitle: 'Upload a CSV file to create new records from the fixed template.',
  fields: {
    file: 'CSV file',
  },
  // Human labels for the advanced lead-import wizard's mappable fields and
  // configuration-step global fields (spec 0033): the backend sends these
  // paths as `ImportDefinition::fields()`/`globalConfig()` label keys
  // (`imports.leads.fields.*` / `imports.leads.global.*`), resolved here.
  leads: {
    fields: {
      full_name: 'Full name',
      first_name: 'First name',
      last_name: 'Last name',
      company_name: 'Company name',
      tax_code: 'Tax code',
      vat_number: 'VAT number',
      email: 'Email',
      phone: 'Phone',
      mobile: 'Mobile',
      street: 'Street',
      postal_code: 'Postal code',
      country: 'Country',
      region: 'Region',
      province: 'Province',
      city: 'City',
      notes: 'Notes',
      campaign_code: 'Campaign code',
    },
    global: {
      campaign_id: 'Campaign',
      project_id: 'Project',
      source_id: 'Source',
      product_ids: 'Products of interest',
    },
  },
  buttons: {
    downloadTemplate: 'Download template',
    upload: 'Upload',
    uploading: 'Uploading…',
    confirm: 'Confirm',
    confirming: 'Confirming…',
    downloadErrorReport: 'Download error report',
    close: 'Close',
  },
  status: {
    validating: 'Validating',
    awaiting_confirmation: 'Awaiting confirmation',
    processing: 'Importing',
    completed: 'Completed',
    failed: 'Failed',
  },
  background: {
    hint: 'The operation keeps running on the server even if you close this dialog.',
    stalled:
      'This is taking longer than expected, so we stopped checking. The server still owns the run — check again in a moment or, if it does not move, tell your administrator.',
    retry: 'Check again',
  },
  summary: {
    total: 'Total rows',
    valid: 'Valid rows',
    invalid: 'Discarded rows',
    imported: 'Imported rows',
  },
  preview: {
    validSampleTitle: 'Valid rows (sample)',
    invalidSampleTitle: 'Discarded rows',
    rowNumber: 'Row',
    values: 'Values',
    reason: 'Reason',
  },
  errors: {
    fileRequired: 'Please choose a CSV file.',
    forbidden: "You don't have permission to import this data.",
    validation: 'The uploaded file could not be validated. Check the format and try again.',
    invalidState: 'This import can no longer be confirmed.',
    generic: 'Something went wrong. Please try again.',
    jobFailed: 'The import failed. Please try again.',
    templateDownloadError: 'Unable to download the template. Please try again.',
    reportDownloadError: 'Unable to download the error report. Please try again.',
  },
}
