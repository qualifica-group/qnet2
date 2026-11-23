import { useCallback } from 'react'
import { DocumentChecklistForm } from '@/features/document-checklists/document-checklist-form'
import type { DocumentChecklistWithPermissions } from '@/features/document-checklists/types'

interface DocumentChecklistDetailViewProps {
  checklist: DocumentChecklistWithPermissions
  /** Called after a save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a checklist: the editable form itself (entries, sync state and
 * the latest ArchivioSmart calls), saved in place with the normal PUT.
 */
export function DocumentChecklistDetailView({ checklist, onChanged }: DocumentChecklistDetailViewProps) {
  const handleSuccess = useCallback(() => onChanged?.(), [onChanged])

  return (
    <DocumentChecklistForm
      checklist={checklist}
      canEdit={checklist.permissions.resource.update}
      onSuccess={handleSuccess}
      onCancel={handleSuccess}
    />
  )
}
