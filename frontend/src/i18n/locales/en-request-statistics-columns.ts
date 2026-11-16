/** "Columns and statuses" of the Request Management statistics (spec 0391). */

export const requestStatisticsColumns = {
  open: 'Columns and statuses',
  title: 'Columns and statuses',
  description:
    'Choose which working statuses each column counts. The "selected period" column uses the same choice and counts only the requests created in the period.',
  columnsLabel: 'Columns',
  columns: {
    unhandled_new_contacts: 'Unhandled New Contacts',
    current_potentials: 'Potential Leads',
    associati: 'Enrolled',
    trattative_concluse: 'Deals Closed',
    invio_presa_in_carico: 'Handover Sent',
  },
  twinNote: 'Also applies to "{{column}} (selected period)".',
  periodNote: 'Counts the requests created in the selected period.',
  searchPlaceholder: 'Search workflow, category or status…',
  noMatch: 'No workflow or status found.',
  inactive: 'Inactive',
  noStatuses: 'No status in this workflow.',
  chosenCount: '{{count}} chosen',
  selectAll: 'All',
  selectNone: 'None',
  loadError: 'Unable to load the column configuration.',
  saveError: 'Unable to save the column configuration.',
  saved: 'Column configuration saved.',
  actions: {
    reset: 'Reset',
    cancel: 'Cancel',
    save: 'Save',
  },
}
