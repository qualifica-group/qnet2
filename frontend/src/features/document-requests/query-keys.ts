/** Centralized query keys of the document requests feature (spec 0428). */
export const documentRequestKeys = {
  list: (workOrderId: number) => ['work-orders', 'document-requests', workOrderId] as const,
  defaults: (workOrderId: number) => ['work-orders', 'document-requests', workOrderId, 'defaults'] as const,
  syncLogs: (requestId: number) => ['document-requests', 'sync-logs', requestId] as const,
}
