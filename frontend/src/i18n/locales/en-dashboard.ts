/**
 * Dashboard home (spec 0151): "Activities to complete" section (D-2..D-6),
 * Time tracking, module statistics block headers, empty state (D-9). Mirrors `it-dashboard.ts`.
 */

export const dashboard = {
  tasksSection: {
    title: 'Activities to complete',
    estimatedTime: 'Estimated time',
    toValidate: 'To validate',
    loadError: 'The dashboard counters are not available right now.',
    cards: {
      not_completed: {
        label: 'All',
        description: 'Open workload that still needs completion.',
      },
      assigned_to_me: {
        label: 'Assigned to me',
        description: 'Activities where you are currently an assignee.',
      },
      assigned_by_me: {
        label: 'Assigned by me',
        description: 'Activities you assigned to other users.',
      },
      created_by_me: {
        label: 'Created by me',
        description: 'Activities you created for other people.',
      },
      observed_by_me: {
        label: 'Observed by me',
        description: 'Activities you are following as an observer.',
      },
    },
  },
  timeEntriesSection: {
    title: 'Time tracking',
  },
  moduleSections: {
    opportunities: 'Opportunities',
    quotes: 'Quotes',
    leads: 'Leads',
    registries: 'Registries',
  },
  empty: {
    title: 'No content available',
    description: "You don't have permission to view any block of this page.",
  },
}
