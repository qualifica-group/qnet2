/**
 * Localized strings for the generic per-table export feature (spec 0014):
 * the format choice, the grid-state summary, the processing/download
 * progress view and the error messages surfaced by the frozen export
 * contract.
 *
 * Extracted into its own module to keep `en.ts` within the engineering size
 * limits (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is
 * unchanged.
 */
export const exports = {
  /** Toolbar action label (opens the export dialog, gated by `{resource}.export`). */
  action: 'Export',
  title: 'Export data',
  subtitle: 'Export the current view exactly as filtered, sorted and displayed.',
  fields: {
    format: 'File format',
  },
  formats: {
    csv: 'CSV',
    xlsx: 'Excel (XLSX)',
  },
  stateSummary: {
    columns: 'Columns',
    filters: 'Active filters',
    sort: 'Sort',
    sortActive: 'Applied',
    sortNone: 'None',
    search: 'Search',
    searchNone: 'None',
  },
  buttons: {
    export: 'Export',
    exporting: 'Exporting…',
    download: 'Download file',
    downloading: 'Downloading…',
    close: 'Close',
  },
  status: {
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
  },
  rowCount_one: '{{count}} row exported',
  rowCount_other: '{{count}} rows exported',
  errors: {
    validation: 'The export request could not be validated. Please try again.',
    rateLimited: 'Too many requests. Please wait a moment and try again.',
    generic: 'Something went wrong. Please try again.',
    jobFailed: 'The export failed. Please try again.',
  },
}
