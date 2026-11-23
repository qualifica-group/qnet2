import { useFieldArray, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { buildDefaultSection, NO_SECTION_KEY } from '@/features/document-checklists/document-checklist-form-values'
import {
  CHECKLIST_MAX_SECTIONS,
  type DocumentChecklistFormValues,
} from '@/features/document-checklists/document-checklist-schema'

/**
 * Groups of documents of the checklist form (spec 0427): add, move, remove.
 * Items reference a group by its client `key`, so reordering never breaks the
 * link; removing a group sends its items back to "no group" (D-7).
 */
export function useChecklistSections(
  control: Control<DocumentChecklistFormValues>,
  setValue: UseFormSetValue<DocumentChecklistFormValues>,
) {
  const { fields, append, remove, move } = useFieldArray({ control, name: 'sections' })
  const sections = useWatch({ control, name: 'sections' })
  const items = useWatch({ control, name: 'items' })

  const itemCount = (key: string) => items.filter((item) => item.section_key === key).length

  const removeSection = (index: number) => {
    const key = sections[index]?.key
    items.forEach((item, itemIndex) => {
      if (item.section_key === key) {
        setValue(`items.${itemIndex}.section_key`, NO_SECTION_KEY, { shouldDirty: true })
      }
    })
    remove(index)
  }

  const moveBy = (index: number, offset: -1 | 1) => {
    const target = index + offset
    if (target >= 0 && target < fields.length) {
      move(index, target)
    }
  }

  return {
    fields,
    canAdd: fields.length < CHECKLIST_MAX_SECTIONS,
    itemCount,
    addSection: () => append(buildDefaultSection()),
    removeSection,
    moveUp: (index: number) => moveBy(index, -1),
    moveDown: (index: number) => moveBy(index, 1),
  }
}
