import { useResourcePermissions } from '@/features/authorization/permissions'

/**
 * Whether every field an editor's cascade writes is editable too (spec 0195
 * D-6c): otherwise the editor must not open, or its save would carry a write
 * the server refuses. Shared by every record whose rows cascade (Opportunita',
 * Lead, Progetti, Campagne).
 */
export function useCascadeEditable(fields: readonly string[]): boolean {
  const { field } = useResourcePermissions()
  return fields.every((name) => {
    const permission = field(name)
    return permission.editable && !permission.disabled
  })
}
