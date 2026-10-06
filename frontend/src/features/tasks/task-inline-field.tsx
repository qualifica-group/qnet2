import { useEffect, useRef, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, type FieldError, type FieldErrors } from 'react-hook-form'
import { Check, Loader2, Pencil, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { RecordField } from '@/components/detail/record-panel'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { cn } from '@/lib/utils'
import type { TaskInlineEdit } from '@/features/tasks/use-task-inline-edit'

/** What the editor focuses on open: a text-like input, the rich-text surface or a picker trigger. */
const FOCUSABLE_EDITOR_SELECTOR = 'input:not([type="hidden"]), [contenteditable="true"], [role="combobox"]'

/** A click on one of these inside the displayed value keeps its own meaning (record link, person card). */
const INTERACTIVE_SELECTOR = 'a, button, input, [role="button"]'

/** Enter in one of these confirms the edit (a rich-text Enter is a new line, a picker's opens it). */
const CONFIRM_ON_ENTER_INPUT_TYPES = new Set(['text', 'date', 'time', 'number'])

/**
 * The row already names the field in its label column: the control's own
 * `FormLabel` stays for assistive tech (it is what names the control) but is
 * hidden from sight, so the label never reads twice.
 */
const ROW_CONTROL_CLASS = 'min-w-0 [&_[data-slot=form-label]]:sr-only'

interface TaskFieldRowProps {
  /** The field's `metaKey`: a hidden field drops the whole row, label included. */
  field: string
  label: string
  icon?: ReactNode
  /** The field's control (a `MetaField`-based component): it keeps its own messages and hints. */
  children: ReactNode
}

/**
 * One OPEN field row of a task record (spec 0195): the detail's own row
 * layout — label column, value column — with the control as the value. The
 * create form is made of these, and the detail shows an inline editor in one,
 * so both read exactly like the detail.
 */
export function TaskFieldRow({ field, label, icon, children }: TaskFieldRowProps) {
  const { field: fieldPermission } = useResourcePermissions()

  if (!fieldPermission(field).visible) {
    return null
  }

  return (
    <RecordField label={label} icon={icon}>
      <div className={ROW_CONTROL_CLASS}>{children}</div>
    </RecordField>
  )
}

interface TaskInlineFieldProps {
  /** The form field (and its `metaKey`) this row edits: gates the row and keys the single open editor. */
  field: string
  label: string
  icon?: ReactNode
  inline: TaskInlineEdit
  /** The control shown while editing: the SAME field component the create form uses (spec 0195 D-3). */
  editor: ReactNode
  /** Extra condition on top of the field permission (e.g. the referent needs an anagrafica). */
  canEdit?: boolean
  /** The persisted value, as the read-only detail always rendered it. */
  children: ReactNode
}

/**
 * One row of the task detail that edits in place (spec 0195 D-2): the
 * persisted value as before, a pencil (on hover with a mouse, always on
 * touch) — or a click on the value itself — opens the field's own control
 * with Confirm/Cancel. Confirm PATCHes that field alone; Cancel or Esc
 * restores it. Hidden fields render nothing, non-editable ones no affordance
 * (D-6), exactly as `MetaField` would decide.
 */
export function TaskInlineField({
  field,
  label,
  icon,
  inline,
  editor,
  canEdit = true,
  children,
}: TaskInlineFieldProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { getFieldState, formState } = useFormContext()
  const editorRef = useRef<HTMLDivElement>(null)
  const permission = fieldPermission(field)
  // A refused create (or a "Fatto" that failed validation) leaves the row
  // closed: its message has to show there, not only inside the editor.
  const closedError = firstMessage(getFieldState(field, formState).error)
  const isEditing = inline.editingField === field
  const editable = canEdit && permission.editable && !permission.disabled

  // Moves focus into the control that just replaced the value (DOM sync only).
  useEffect(() => {
    if (isEditing) {
      editorRef.current?.querySelector<HTMLElement>(FOCUSABLE_EDITOR_SELECTOR)?.focus()
    }
  }, [isEditing])

  if (!permission.visible) {
    return null
  }

  if (isEditing) {
    const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
      // Only keys pressed INSIDE this row: a picker's popover is portalled
      // elsewhere in the DOM and closes itself on Esc.
      if (!(event.target instanceof Node) || !event.currentTarget.contains(event.target)) {
        return
      }
      if (event.key === 'Escape') {
        event.preventDefault()
        inline.cancel()
      } else if (
        event.key === 'Enter' &&
        event.target instanceof HTMLInputElement &&
        CONFIRM_ON_ENTER_INPUT_TYPES.has(event.target.type)
      ) {
        event.preventDefault()
        inline.save()
      }
    }

    return (
      <div ref={editorRef} onKeyDown={handleKeyDown}>
        <TaskFieldRow field={field} label={label} icon={icon}>
          <div className="flex flex-col gap-2">
            {editor}
            {inline.error ? (
              <p role="alert" className="text-xs font-medium text-destructive">
                {inline.error}
              </p>
            ) : null}
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" size="sm" onClick={inline.cancel} disabled={inline.isSaving}>
                <X aria-hidden="true" />
                {inline.cancelLabel}
              </Button>
              <Button type="button" size="sm" onClick={inline.save} disabled={inline.isSaving}>
                {inline.isSaving ? (
                  <Loader2 className="animate-spin" aria-hidden="true" />
                ) : (
                  <Check aria-hidden="true" />
                )}
                {inline.confirmLabel}
              </Button>
            </div>
          </div>
        </TaskFieldRow>
      </div>
    )
  }

  const handleValueClick = (event: MouseEvent<HTMLDivElement>) => {
    if (event.target instanceof Element && event.target.closest(INTERACTIVE_SELECTOR)) {
      return
    }
    inline.start(field)
  }

  return (
    <RecordField label={label} icon={icon}>
      {editable ? (
        <div className="group flex min-w-0 items-start gap-1.5">
          {/* Mouse shortcut only: the pencil is the keyboard/screen-reader path. */}
          <div className="min-w-0 flex-1 cursor-pointer" onClick={handleValueClick}>
            {children}
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className={cn(
              'size-6 shrink-0 text-muted-foreground',
              'pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100 focus-visible:opacity-100',
            )}
            aria-label={t('tasks.detail.inlineEdit.edit', { field: label })}
            onClick={() => inline.start(field)}
          >
            <Pencil className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      ) : (
        children
      )}
      {closedError ? (
        <p role="alert" className="mt-1 text-xs font-medium text-destructive">
          {closedError}
        </p>
      ) : null}
    </RecordField>
  )
}

/** The first message of a field's error, nested ones included (the recurrence rule is one field of many keys). */
function firstMessage(error: FieldError | FieldErrors | undefined): string | null {
  if (!error) {
    return null
  }
  if (typeof error.message === 'string' && error.message !== '') {
    return error.message
  }
  for (const child of Object.values(error)) {
    if (child && typeof child === 'object' && child !== error.ref) {
      const message = firstMessage(child as FieldErrors)
      if (message) {
        return message
      }
    }
  }
  return null
}
