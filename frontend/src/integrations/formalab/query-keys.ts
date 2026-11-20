export const formalabKeys = {
  status: ['formalab', 'status'] as const,
  workOrderLink: (workOrderId: number) => ['formalab', 'work-order-link', workOrderId] as const,
}
