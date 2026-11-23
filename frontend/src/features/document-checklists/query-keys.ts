/** Centralized query keys of the document checklists feature (spec 0403). */
export const documentChecklistKeys = {
  all: ['document-checklists'] as const,
  detail: (id: number) => ['document-checklists', 'detail', id] as const,
  syncLogs: (id: number) => ['document-checklists', 'sync-logs', id] as const,
  settings: ['archivio-smart', 'settings'] as const,
}
