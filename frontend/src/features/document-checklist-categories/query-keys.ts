/** Centralized query keys of the document checklist categories feature (spec 0410). */
export const documentChecklistCategoryKeys = {
  all: ['document-checklist-categories'] as const,
  detail: (id: number) => ['document-checklist-categories', 'detail', id] as const,
  syncLogs: (id: number) => ['document-checklist-categories', 'sync-logs', id] as const,
}
