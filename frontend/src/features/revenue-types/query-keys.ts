/** Query keys of the revenue sub-account picker. */
export const revenueTypeKeys = {
  all: ['revenue-types', 'for-select'] as const,
  list: (search: string) => ['revenue-types', 'for-select', 'list', { search }] as const,
  labels: (ids: number[]) => ['revenue-types', 'for-select', 'labels', { ids }] as const,
}
