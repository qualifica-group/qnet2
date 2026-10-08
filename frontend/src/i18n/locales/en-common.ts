/** Cross-module UI strings (`common.*`): split out of `en.ts` for the engineering size limit. */
export const common = {
  loading: 'Loading…',
  retry: 'Retry',
  search: 'Search',
  notFound: 'Page not found',
  backToDashboard: 'Back to dashboard',
  comingSoon: 'This section is not available yet.',
  clear: 'Clear',
  confirm: 'Confirm',
  cancel: 'Cancel',
  confirmTitle: 'Are you sure?',
  yes: 'Yes',
  no: 'No',
  back: 'Back',
  edit: 'Edit',
  new: 'New',
  viewProfile: "View {{name}}'s profile",
  /** Label of the select a tab strip collapses into when the tabs no longer fit. */
  tabsSelectLabel: 'Section',
  /** Appended to the name when duplicating a record (row action "duplicate"); leading space by design. */
  /** Accessible name of the button that removes one chip from a multi-select. */
  remove: 'Remove',
  close: 'Close',
  /** Sheet toolbar action that leaves the modal for the record's dedicated detail page. */
  openDetailPage: 'Open detail page',
  copySuffix: ' (copy)',
  /** Final state of a record fetch answered 404/403 (`DetailError`, `RecordUnavailable`): no retry. */
  recordUnavailable: {
    notFound: {
      title: 'Record not found',
      description: 'The record you are looking for does not exist or has been deleted.',
    },
    forbidden: {
      title: 'Access denied',
      description: 'You do not have the necessary permissions to view this record.',
    },
  },
  /** In-place editing of a record's rows (spec 0195): the pencil, and the open row's confirm/cancel. */
  inlineEdit: {
    edit: 'Edit {{field}}',
    save: 'Save',
    apply: 'Done',
    revert: 'Revert',
    cancel: 'Cancel',
  },
}
