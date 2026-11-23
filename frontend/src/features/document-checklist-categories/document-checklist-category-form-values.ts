import type { DocumentChecklistCategoryFormValues } from '@/features/document-checklist-categories/document-checklist-category-schema'
import type {
  DocumentChecklistCategory,
  DocumentChecklistCategoryPayload,
} from '@/features/document-checklist-categories/types'

export function buildDefaultCategoryValues(): DocumentChecklistCategoryFormValues {
  return { name: '', description: '', sort_order: 0 }
}

export function categoryToFormValues(category: DocumentChecklistCategory): DocumentChecklistCategoryFormValues {
  return { name: category.name, description: category.description ?? '', sort_order: category.sort_order }
}

export function buildCategoryPayload(values: DocumentChecklistCategoryFormValues): DocumentChecklistCategoryPayload {
  const description = values.description.trim()
  return { name: values.name.trim(), description: description === '' ? null : description, sort_order: values.sort_order }
}
