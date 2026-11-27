/** Centralized query keys of the document requests feature (spec 0428). */
export const documentRequestKeys = {
  /** Prefix of every work order list: a refresh from the checklist touches requests of many work orders. */
  lists: ['work-orders', 'document-requests'] as const,
  list: (workOrderId: number) => ['work-orders', 'document-requests', workOrderId] as const,
  defaults: (workOrderId: number) => ['work-orders', 'document-requests', workOrderId, 'defaults'] as const,
  syncLogs: (requestId: number) => ['document-requests', 'sync-logs', requestId] as const,
  checklistOpenRequests: (checklistId: number) => ['document-checklists', 'open-requests', checklistId] as const,
}
