import { useCallback } from 'react'
import { DocumentChecklistCategoryForm } from '@/features/document-checklist-categories/document-checklist-category-form'
import type { DocumentChecklistCategoryWithPermissions } from '@/features/document-checklist-categories/types'

interface DocumentChecklistCategoryDetailViewProps {
  category: DocumentChecklistCategoryWithPermissions
  /** Called after a save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/** Detail of a category: the editable form itself plus the latest ArchivioSmart calls. */
export function DocumentChecklistCategoryDetailView({ category, onChanged }: DocumentChecklistCategoryDetailViewProps) {
  const handleSuccess = useCallback(() => onChanged?.(), [onChanged])

  return (
    <DocumentChecklistCategoryForm
      category={category}
      canEdit={category.permissions.resource.update}
      onSuccess={handleSuccess}
      onCancel={handleSuccess}
    />
  )
}
