export const formalabKeys = {
  status: ['formalab', 'status'] as const,
  classes: (search: string) => ['formalab', 'classes', search] as const,
  workOrderLink: (workOrderId: number) => ['formalab', 'work-order-link', workOrderId] as const,
}
