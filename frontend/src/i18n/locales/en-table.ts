/**
 * The generic table framework's strings (toolbar, saved views, bulk actions).
 * Split out of `en.ts` to keep it within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); spread back under the `table` key so
 * `t('table.xxx')` call sites are unaffected.
 */
export const table = {
  // The default hidden `id` column injected into every table
  // (AbstractTableDefinition / InjectsDefaultIdColumn).
  columns: {
    id: 'ID',
  },
  actionsHeader: 'Actions',
  rowActions: 'Row actions',
  moreActions: 'More actions',
  search: 'Search…',
  searchPlaceholder: 'Search {{columns}}…',
  rowCount_one: '{{count}} row',
  rowCount_other: '{{count}} rows',
  options: 'Table options',
  export: 'Export',
  fullscreen: 'Fullscreen',
  exitFullscreen: 'Exit fullscreen',
  confirmAction: 'Are you sure you want to perform this action?',
  loadError: 'Unable to load the table. Please try again.',
  cellUpdateError: 'Unable to save the change.',
  emptyConfig: 'No columns are available for this table.',
  noRows: 'No records to show.',
  resetLayout: 'Reset layout',
  layoutReset: 'Table layout reset to default.',
  layoutError: 'Unable to update the table layout.',
  resetFilters: 'Reset filters',
  filtersReset: 'Table filters cleared.',
  filtersError: 'Unable to reset the table filters.',
  filterValuesTruncated:
    'Showing only the first matching values. Use a filter condition to narrow further.',
  textFilters: 'Text Filters',
  numberFilters: 'Number Filters',
  dateFilters: 'Date Filters',
  primaryContactsCount: '{{count}} primary contacts',
  copy: 'Copy',
  copied: 'Copied',
  savedFilters: 'Saved filters',
  savedFiltersSubtitle: 'Reuse a filter set in one click.',
  saveViewHeading: 'Save current view',
  saveView: 'Save view',
  applyFilterToSaveHint: 'Apply a filter first to save it as a view.',
  viewActive: 'Active',
  viewNamePlaceholder: 'View name',
  visibility: 'Visibility',
  visibilityPrivate: 'Private',
  visibilityShared: 'Shared',
  myViews: 'My views',
  sharedViews: 'Shared',
  sharedBy: 'Shared by {{name}}',
  applyView: 'Apply view',
  deleteView: 'Delete view',
  save: 'Save',
  viewSaved: 'Filter view saved.',
  viewSaveError: 'Unable to save the filter view.',
  viewDeleted: 'Filter view deleted.',
  viewDeleteError: 'Unable to delete the filter view.',
  duplicateViewName: 'You already have a view with this name.',
  noSavedViews: 'No saved views yet.',
  selectedCount_one: '{{count}} row selected',
  selectedCount_other: '{{count}} rows selected',
  bulkActions: 'Actions ({{count}})',
  deleteSelected: 'Delete selected ({{count}})',
  bulkDeleteConfirmTitle: 'Delete selected rows',
  bulkDeleteConfirmBody_one:
    'This will permanently delete {{count}} selected row. This action cannot be undone.',
  bulkDeleteConfirmBody_other:
    'This will permanently delete {{count}} selected rows. This action cannot be undone.',
  bulkDeleted_one: '{{count}} row deleted.',
  bulkDeleted_other: '{{count}} rows deleted.',
  bulkDeletePartial:
    '{{deleted}} rows deleted, {{failed}} could not be deleted.',
  bulkDeleteError: 'Unable to delete the selected rows. Please try again.',
  relationEditor: {
    placeholder: 'Select…',
    searchPlaceholder: 'Search…',
    empty: 'No results.',
    error: 'Unable to load the options.',
    clear: 'Clear',
    trigger: 'Pick a value',
    retry: 'Retry',
    loadMore: 'Load more',
  },
  multiSelectEditor: {
    list: 'Pick one or more values',
    searchPlaceholder: 'Search…',
    empty: 'No results.',
    error: 'Could not load the options.',
    retry: 'Retry',
    loadMore: 'Load more',
    noScope: 'This row has no scope yet: unlock the whole catalogue to pick.',
    hintScoped: "Only the options within this row's scope.",
    // Spec 0075, D-4: the scope cannot be lifted here — this domain refuses
    // what falls outside it, so there is no unlock to explain.
    hintLocked: "Only the options within this row's scope: this module refuses the others.",
    hintUnlocked: "Whole catalogue: a pick outside the scope will extend this row's scope.",
    unlock: 'Show all',
    relock: "Limit to the row's scope",
    unlockTitle: 'Show the whole catalogue?',
    unlockDescription:
      "Picking an item outside this row's scope will automatically extend that scope to include it.",
  },
  // Spec 0075: the in-cell {business function, product category} editor — the
  // same two-step flow as the form's own ProductLinesField.
  productLinesEditor: {
    selected: 'Product categories on this record',
    none: 'No product category yet.',
    remove: 'Remove {{name}}',
    back: 'Back to the business functions',
    businessFunctionStep: 'Step 1: pick the business function.',
    categoryStep: 'Step 2: pick a product category of {{name}}.',
    businessFunctionSearch: 'Search business functions…',
    categorySearch: 'Search product categories…',
    empty: 'No results.',
    error: 'Could not load the options.',
    retry: 'Retry',
    loadMore: 'Load more',
    uncoveredProducts:
      'These products of interest would no longer be covered by any product category: {{names}}. The save will be refused.',
  },
  selectEditor: {
    list: 'Pick a value',
    empty: 'No value available for this row.',
  },
  // The editor is a group of two inputs since the time became optional (user
  // directive 2026-07-31): `label` names the group, the other two the inputs.
  dateTimeEditor: {
    label: 'Date and time',
    dateLabel: 'Date',
    timeLabel: 'Time (optional)',
    clear: 'Clear',
  },
  // Spec 0064: the date-only twin of `dateTimeEditor`, used by a Product
  // Category attribute of type `date` (no time component) — a distinct label
  // so the control is never announced as "Date and time".
  dateEditor: {
    label: 'Date',
  },
  noteDialog: {
    title: 'Add a note',
    description: 'This status requires an explanatory note before it can be saved.',
    label: 'Note',
    required: 'A note is required.',
    cancel: 'Cancel',
    confirm: 'Save',
  },
  advancedFilters: {
    toggle: 'Advanced filters',
    activeCount_one: '{{count}} active filter',
    activeCount_other: '{{count}} active filters',
    apply: 'Apply',
    reset: 'Reset',
    requiredError: 'This field is required.',
    rangeFrom: 'From',
    rangeTo: 'To',
    rangeSeparator: '–',
    selectPlaceholder: 'Select…',
    searchPlaceholder: 'Search…',
    empty: 'No results.',
    loadError: 'Unable to load the options.',
    clearLabel: 'Clear',
    removeLabel: 'Remove',
  },
}
