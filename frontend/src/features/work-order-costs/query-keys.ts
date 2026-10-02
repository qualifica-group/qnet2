/** Centralized TanStack Query keys of the work order costs section (spec 0190). */
export const workOrderCostKeys = {
  overview: (workOrderId: number) => ['work-orders', 'costs', workOrderId] as const,
}
