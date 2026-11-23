/**
 * Charts dashboard (spec 0107): shown by the Request Management Statistics page (spec 0185);
 * category/operator names stay domain values, never i18n keys.
 */
export const requestManagementDashboard = {
  regionLabel: 'Request Management dashboard',
  pageForbidden: "You don't have permission to view Request Management Statistics.",
  editFilters: 'Filters',
  // Spec 0192: one-click periods in the toolbar.
  periods: {
    label: 'Period',
    today: 'Today',
    yesterday: 'Yesterday',
    last7Days: 'Last 7 days',
    thisMonth: 'This month',
    lastMonth: 'Last month',
    all: 'All time',
    custom: 'Custom',
  },
  tabsLabel: 'Statistics sections',
  overviewTab: 'Overview',
  applied: {
    title: 'Applied filters',
    period: 'Period',
    periodValue: '{{from}} – {{to}}',
    // Spec 0169 D-5: open bounds.
    periodFrom: 'from {{from}}',
    periodTo: 'up to {{to}}',
    periodAll: 'All time',
    categories: 'Categories',
    sites: 'Sites',
    operators: 'Operators',
    rowMode: 'Rows',
    more: '+{{count}}',
    allOperatorsOfSites: 'All of the selected sites',
  },
  noCategories:
    'Nothing to show yet: there are no requests in the categories included in the report. The dashboard will fill in as soon as one is recorded.',
  errors: {
    network: 'Unable to reach the server: check your connection and try again.',
    invalidFilters:
      'The applied filters are no longer valid (for example a category is no longer available). Update them from the Filters button.',
    generic: 'Unable to load the dashboard because of a server error. Please try again shortly.',
  },
  empty: 'No charts to show for this selection.',
  // Spec 0141: a reportable category may have no column configured.
  tilesEmpty: 'No columns configured for this category.',
  overall: 'Overall',
  indicatorsChartTitle: 'Indicator profile',
  // Spec 0192 D-4: comparison with the previous period of equal length.
  trend: {
    new: 'New',
    vsPrevious: 'Previous period: {{value}}',
  },
  shares: {
    title: 'Split by category',
    others: 'Other categories',
    item: '{{label}}: {{value}} ({{percent}}%)',
    lead: '{{label}} {{percent}}%',
    more: '+{{count}} more',
  },
  breakdown: {
    title: 'Split by current status',
    trigger: '{{label}} split by status',
    item: '{{label}}: {{value}}',
  },
  heatmap: {
    title: 'Categories × indicators map',
    description: 'Darker = higher value for that indicator. Click a category to open it.',
    category: 'Category',
    notConfigured: 'Not configured',
  },
  leaderboard: {
    title: 'Operator ranking',
    rank: 'Rank',
    operator: 'Operator',
  },
}
