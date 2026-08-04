/** Centralized TanStack Query keys for the field-change-requests domain (spec 0078). */
export const fieldChangeRequestKeys = {
  all: ['field-change-requests'] as const,
  detail: (id: number) => ['field-change-requests', 'detail', id] as const,
  forRecord: (resource: string, subjectId: number) =>
    ['field-change-requests', 'for-record', resource, subjectId] as const,
}
