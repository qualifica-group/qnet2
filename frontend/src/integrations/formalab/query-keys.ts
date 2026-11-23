export const formalabKeys = {
  status: ['formalab', 'status'] as const,
  syncState: ['formalab', 'sync-state'] as const,
  workOrderLinks: ['formalab', 'work-order-link'] as const,
  workOrderLink: (workOrderId: number) => ['formalab', 'work-order-link', workOrderId] as const,
  allCategorySettings: ['formalab', 'category-settings'] as const,
  categorySettings: (categoryId: number) => ['formalab', 'category-settings', categoryId] as const,
}
