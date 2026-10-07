/** Centralized TanStack Query keys of the work order contract data tab (spec 0201). */
export const workOrderContractDataKeys = {
  detail: (workOrderId: number) => ['work-orders', 'contract-data', workOrderId] as const,
}
