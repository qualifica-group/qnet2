/** Centralized TanStack Query keys of the work order workspace page (spec 0420). */
export const workOrderPageKeys = {
  page: (workOrderId: number) => ['work-orders', 'page', workOrderId] as const,
}
