export const formalabKeys = {
  status: ['formalab', 'status'] as const,
  syncState: ['formalab', 'sync-state'] as const,
  workOrderLinks: ['formalab', 'work-order-link'] as const,
  workOrderLink: (workOrderId: number) => ['formalab', 'work-order-link', workOrderId] as const,
  allCategorySettings: ['formalab', 'category-settings'] as const,
  categorySettings: (categoryId: number) => ['formalab', 'category-settings', categoryId] as const,
  learnerStatements: (workOrderId: number) => ['formalab', 'learner-statement', workOrderId] as const,
  learnerStatement: (workOrderId: number, scenario: string | null) => ['formalab', 'learner-statement', workOrderId, scenario] as const,
}
