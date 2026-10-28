import { migrationRequirements } from '@/i18n/locales/en-migration-requirements'

/**
 * Localized strings for the external data migrations module (spec 0013):
 * the super-admin-only preview (fase 1) page and the import (fase 2)
 * confirm/progress/summary dialog.
 *
 * Extracted into its own module to keep `en.ts` within the engineering size
 * limits (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is
 * unchanged.
 */
export const migrations = {
  nav: {
    label: 'Migrations',
  },
  sources: {
    roles: 'Roles',
    users: 'Users',
    'business-functions': 'Business functions',
    companies: 'Companies',
    'company-sites': 'Company sites',
    'operational-sites': 'Operational sites',
    'business-function-members': 'Business functions — link manager & operators',
    'referent-types': 'Referent types',
    referents: 'Referents',
    sources: 'Sources',
    tags: 'Tags',
    sectors: 'Sectors',
    'task-templates': 'Task templates',
    'vat-rates': 'VAT rates',
    'payment-methods': 'Payment methods',
    attributes: 'Attributes',
    'product-categories': 'Product categories',
    'product-category-attributes': 'Product categories — link attributes',
    'attribute-layouts': 'Attribute layouts',
    products: 'Products',
    'cost-products': 'Cost products',
    'email-templates': 'Email templates',
    'document-bundles': 'Document bundles',
    registries: 'Registries',
    opportunities: 'Opportunities',
    quotes: 'Quotes',
    'work-orders': 'Work orders',
    tasks: 'Tasks',
    'work-order-progress': 'Work orders — progress',
    'product-suppliers': 'Products — link suppliers',
    'commission-configurations': 'Commission configurations',
    'purchase-requests': 'Purchase requests',
    'purchase-orders': 'Purchase orders',
    'financial-accounts': 'Financial accounts',
    'purchase-invoices': 'Purchase invoices',
    'proforma-requests': 'Proforma requests',
    invoices: 'Invoices',
    collections: 'Collections',
    notes: 'Notes',
  },
  state: {
    notMigrated: 'Not migrated',
    running: 'In progress',
    failed: 'Last import failed',
    partial: 'Migrated with failed rows',
    migrated: 'Migrated',
  },
  overview: {
    title: 'Migration status',
    description: 'The sources in the order to import them. Select a source to see its template and what it needs.',
    progress: '{{migrated}} of {{total}} sources migrated',
    phase: 'Phase {{phase}}',
    unordered: 'No phase',
    migratedAt: 'Migrated on {{date}}',
    lastRunAt: 'Last import on {{date}}',
    failedRows_one: '{{count}} failed row',
    failedRows_other: '{{count}} failed rows',
  },
  requirements: {
    title: 'What it takes to migrate well',
    dependenciesTitle: 'Import first',
    none: 'No source to import first.',
    required: 'Required',
    optional: 'Recommended',
    legend:
      'Required: without it the rows are not imported. Recommended: without it the link stays empty with a warning in the report.',
    missingRequired: 'Required sources missing: {{sources}}. Import them before this one.',
  },
  sourceRequirements: migrationRequirements,
  page: {
    sourceLabel: 'Source',
    sourcePlaceholder: 'Select a source…',
    sourcesLoadError: 'Unable to load the migration sources. Please try again.',
    sourcesEmpty: 'No migration sources are registered.',
    pageIndicator: 'Page {{page}}',
    previous: 'Previous',
    next: 'Next',
  },
  template: {
    title: 'Expected template',
    description:
      'qnet defines this field schema for {{source}}: the external source must return records matching it exactly.',
    fieldHeader: 'Field',
    typeHeader: 'Type',
    loadError: 'Unable to load the expected template. Please try again.',
    empty: 'This source defines no fields.',
    importButton: 'Import this source',
    importUnavailable:
      'Not available on this installation: users are migrated in link-only mode and function managers and operators are managed in qnet.',
    endpointTitle: 'Expected endpoint',
    method: 'Method: {{method}}',
    copyUrl: 'Copy URL',
    baseUrlMissing: 'The base URL is not configured for this source; only the path is shown.',
    sampleTitle: 'Sample response',
    copyJson: 'Copy JSON',
    copied: 'Copied',
  },
  preview: {
    showButton: 'Show external preview',
    loadError: 'Unable to load the preview. Please try again.',
    empty: 'No records found for this source.',
  },
  import: {
    title: 'Import {{source}}',
    subtitle: 'Review starts the background import; re-running it is safe (idempotent).',
    confirmDescription:
      'This creates the records in qnet from the external source, in the background. Records already imported are skipped, so running it again is safe.',
    start: 'Start import',
    starting: 'Starting…',
    close: 'Close',
    summary: {
      total: 'Total rows',
      created: 'Created',
      skipped: 'Skipped',
      failed: 'Failed',
    },
    reportTitle: 'Warnings and errors',
    reportLevel: {
      warning: 'Warning',
      error: 'Error',
    },
  },
  plan: {
    configureButton: 'Configure order',
    title: 'Mass import order',
    subtitle:
      'Choose which sources “Import all” includes and drag to set the order. Later sources depend on the earlier ones.',
    dragHandle: 'Reorder source',
    enabledLabel: 'Include {{source}} in the mass import',
    unavailable: 'Not available',
    save: 'Save order',
    saving: 'Saving…',
    saved: 'Order saved.',
    loadError: 'Unable to load the import order. Please try again.',
  },
  massImport: {
    button: 'Import all',
    title: 'Import all sources',
    subtitle: 'Runs the enabled sources in the saved order, stopping at the first failure.',
    confirmDescription:
      'This runs every enabled source in order, in the background. It stops at the first source that fails, because later sources depend on the earlier ones. Already-imported records are skipped, so it is safe to run again.',
    start: 'Start import',
    starting: 'Starting…',
    empty: 'No sources are enabled. Enable at least one in “Configure order”.',
    stopped: 'The import stopped at the failing source; the sources after it were not run.',
    sourceStatus: {
      notRun: 'Not run',
    },
    perSourceCounts: '{{created}} created · {{skipped}} skipped · {{failed}} failed',
  },
  background: {
    hint: 'The operation keeps running on the server even if you close this dialog.',
    stalled:
      'This is taking longer than expected, so we stopped checking. The server still owns the run — check again in a moment or, if it does not move, tell your administrator.',
    retry: 'Check again',
  },
  status: {
    pending: 'Pending',
    processing: 'Importing',
    completed: 'Completed',
    failed: 'Failed',
  },
  errors: {
    forbidden: "You don't have permission to access migrations.",
    notFound: 'This migration source does not exist.',
    validation: 'Invalid pagination parameters.',
    externalUnavailable: 'The external system is currently unavailable. Please try again.',
    generic: 'Something went wrong. Please try again.',
    jobFailed: 'The import failed. Please try again.',
  },
}
