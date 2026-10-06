import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { Path, UseFormReturn } from 'react-hook-form'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskInlineEdit } from '@/features/tasks/use-task-inline-edit'

/**
 * The create form's take on the detail's in-place rows (spec 0195 D-8, user
 * directive 2026-10-06): the rows start closed, exactly like the detail, and
 * open one at a time — but nothing is saved per field. "Fatto" keeps the value
 * in the draft (and validates that field, so a mistake shows on its closed
 * row), "Ripristina" puts the draft back as it was when the row opened, cascades
 * included. The header's Salva validates and creates everything at once.
 */
export function useTaskDraftEdit(form: UseFormReturn<TaskFormValues>): TaskInlineEdit {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // The whole draft as the row found it: a cancelled anagrafica pick must
  // bring back the referent it cleared, not only itself.
  const snapshotRef = useRef<TaskFormValues | null>(null)

  const start = (field: string) => {
    snapshotRef.current = structuredClone(form.getValues())
    setEditingField(field)
  }

  const cancel = () => {
    if (snapshotRef.current) {
      form.reset(snapshotRef.current, { keepDefaultValues: true, keepErrors: true, keepDirtyValues: false })
    }
    setEditingField(null)
  }

  const save = () => {
    if (editingField !== null) {
      void form.trigger(editingField as Path<TaskFormValues>)
    }
    setEditingField(null)
  }

  return {
    editingField,
    start,
    cancel,
    save,
    // Nothing is persisted yet: a stray click must not wipe what was typed.
    dismiss: save,
    isSaving: false,
    error: null,
    confirmLabel: t('tasks.detail.inlineEdit.apply'),
    cancelLabel: t('tasks.detail.inlineEdit.revert'),
  }
}
