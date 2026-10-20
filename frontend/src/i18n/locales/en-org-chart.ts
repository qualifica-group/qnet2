/**
 * HR org chart (spec 0273, 0274). Satellite file to keep `en.ts` within the size limits.
 * Mirror of `it-org-chart.ts` — same key set.
 */

export const orgChart = {
  forbidden: 'You do not have permission to view the org chart.',
  loading: 'Loading the org chart…',
  loadError: 'Could not load the org chart. Try again later.',
  tabs: {
    functions: 'Business functions',
    people: 'People',
  },
  type: {
    business_unit: 'Business unit',
    business_service: 'Business service',
  },
  search: 'Search the org chart',
  searchPlaceholder: 'Search by name, job role or function…',
  expand: 'Expand',
  collapse: 'Collapse',
  expandAll: 'Expand all',
  collapseAll: 'Collapse all',
  responsible: 'Manager',
  peopleCount_one: '{{count}} person',
  peopleCount_other: '{{count}} people',
  unassigned: 'Unassigned',
  emptyFunctions: 'No business functions yet.',
  emptyPeople: 'No people to show.',
  noMatch: 'No results for this search.',
  noManager: 'No manager',
  unassignedHint: 'Active people without a business function of membership',
  members: {
    open_one: '{{name}}: {{count}} person, open the list',
    open_other: '{{name}}: {{count}} people, open the list',
    responsible: 'Managers',
    people: 'People',
    empty: 'Nobody in this function.',
  },
  zoom: {
    in: 'Zoom in',
    out: 'Zoom out',
    fit: 'Fit to window',
  },
  move: {
    action: 'Move to…',
    actions: 'Actions for {{name}}',
    title: 'Move {{name}}',
    description: 'Pick the new position: you can confirm before the change is applied.',
    rootPerson: 'No manager (root)',
    rootFunction: 'No parent function (root)',
    select: {
      label: 'Destination',
      placeholder: 'Pick the destination…',
      search: 'Search the destination…',
      empty: 'No destination available.',
      noMatch: 'No destination matches.',
      retry: 'Retry',
    },
    summary: 'Move {{subject}} from "{{from}}" to "{{to}}".',
    functionWarning: "This changes the person's business function of membership.",
    cancel: 'Cancel',
    confirm: 'Confirm the move',
    success: '{{name}} moved.',
    forbidden: 'You do not have permission to make this move.',
    error: 'The move failed. Try again later.',
  },
}
