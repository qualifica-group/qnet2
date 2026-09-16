/**
 * Import module (now a standalone top-level module, `/imports*` — no longer
 * reachable from the Lead module): history rendered by the generic table
 * (domain `import-runs`) and the single run detail page. Kept in a sibling
 * file to hold `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6). Column/action labels of the table are
 * resolved by the generic table engine via `t(column.label)`.
 */

export const leadImports = {
  forbidden: "You don't have permission to view imports.",
  newImport: 'New import',
  columns: {
    date: 'Date',
    operator: 'Operator',
    file: 'File',
    records: 'Records',
    imported: 'Imported',
    errors: 'Errors',
    status: 'Status',
  },
  actions: {
    view: 'View',
    delete: 'Delete',
  },
  deleted: 'Import run deleted successfully.',
  deleteError: 'Unable to delete the import run. Please try again.',
  deleteForbidden: 'You cannot delete this import run.',
  detail: {
    resume: 'Resume import',
    loadError: 'Unable to load the import run. Please try again.',
    sections: {
      stats: 'Statistics',
      metadata: 'Metadata',
      errors: 'Errors',
      records: 'Records',
    },
    stats: {
      total: 'Total rows',
      imported: 'Imported',
      modified: 'Modified',
      invalid: 'Errors',
      warning: 'Warnings',
      duplicate: 'Duplicates',
    },
    metadata: {
      file: 'File',
      globalConfig: 'Global configuration',
      dedupStrategy: 'Duplicate strategy',
      mappedColumns: 'Mapped columns',
      noMetadata: 'No metadata available for this import.',
    },
    gridLabel: 'Imported records (read-only)',
  },
}
