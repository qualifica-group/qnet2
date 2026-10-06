import { useEffect, useRef, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, type FieldError, type FieldErrors } from 'react-hook-form'
import { Check, Loader2, Pencil, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { RecordField } from '@/components/detail/record-panel'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { cn } from '@/lib/utils'
import { useOutsidePointerDismiss } from '@/features/tasks/use-outside-pointer-dismiss'
import type { TaskInlineEdit } from '@/features/tasks/use-task-inline-edit'

/** What the editor focuses on open: a text-like input, the rich-text surface or a picker trigger. */
const FOCUSABLE_EDITOR_SELECTOR = 'input:not([type="hidden"]), [contenteditable="true"], [role="combobox"]'

/** A click on one of these inside the displayed value keeps its own meaning (record link, person card). */
const INTERACTIVE_SELECTOR = 'a, button, input, [role="button"]'

/** Enter in one of these confirms the edit (a rich-text Enter is a new line, a picker's opens it). */
const CONFIRM_ON_ENTER_INPUT_TYPES = new Set(['text', 'date', 'time', 'number'])

/**
 * The open row reads as "being edited" at a glance: the focus color's hairline
 * and halo around the whole row, on a tint veil over the card (a veil, not a
 * new surface — ui-design.md §1-bis), its label promoted to the value's weight.
 */
const EDITING_ROW_CLASS =
  '-mx-2 my-1 rounded-lg border border-ring/50 bg-muted/40 px-2 shadow-sm ring-[3px] ring-ring/15 [&_dt]:font-medium [&_dt]:text-foreground'

/**
 * `row`: the spec-sheet row, label column then value. `block`: a value that
 * names itself (e.g. the recurrence tile) across the section's full width,
 * its label kept for assistive tech only.
 */
type TaskInlineFieldLayout = 'row' | 'block'

interface FieldFrameProps {
  layout: TaskInlineFieldLayout
  label: string
  icon?: ReactNode
  children: ReactNode
}

function FieldFrame({ layout, label, icon, children }: FieldFrameProps) {
  if (layout === 'block') {
    return (
      <div className="flex min-w-0 flex-col py-1">
        <span className="sr-only">{label}</span>
        {children}
      </div>
    )
  }
  return (
    <RecordField label={label} icon={icon}>
      {children}
    </RecordField>
  )
}

interface EditorRowProps extends FieldFrameProps {
  field: string
}

/**
 * The detail's own row layout with the field's control as the value.
 * `MetaFieldRowContext` tells the control's `MetaField` that this row
 * already shows its label: it keeps it for assistive tech only and sets its
 * hint beside the control.
 */
function EditorRow({ field, children, ...frame }: EditorRowProps) {
  return (
    <FieldFrame {...frame}>
      <MetaFieldRowContext.Provider value={field}>{children}</MetaFieldRowContext.Provider>
    </FieldFrame>
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
  /** Defaults to `row`; see `TaskInlineFieldLayout`. */
  layout?: TaskInlineFieldLayout
  /** The persisted value, as the read-only detail always rendered it. */
  children: ReactNode
}

/**
 * One row of the task detail that edits in place (spec 0195 D-2): the
 * persisted value as before, a pencil (on hover with a mouse, always on
 * touch) — or a click on the value itself — opens the field's own control
 * with Confirm/Cancel. Confirm PATCHes that field alone; Cancel or Esc
 * restores it, and a press outside the row closes it (`inline.dismiss`).
 * Hidden fields render nothing, non-editable ones no affordance (D-6),
 * exactly as `MetaField` would decide.
 */
export function TaskInlineField({
  field,
  label,
  icon,
  inline,
  editor,
  canEdit = true,
  layout = 'row',
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
  // A press anywhere outside the open row closes it (user directive 2026-10-06);
  // never mid-save, so the PATCH in flight keeps its editor and its error slot.
  const outsidePointer = useOutsidePointerDismiss(isEditing && !inline.isSaving, inline.dismiss)

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
      <div
        ref={editorRef}
        className={EDITING_ROW_CLASS}
        onKeyDown={handleKeyDown}
        onPointerDownCapture={outsidePointer.onPointerDownCapture}
      >
        <EditorRow field={field} label={label} icon={icon} layout={layout}>
          <div className="flex min-w-0 flex-col gap-2">
            {editor}
            {inline.error ? (
              <p role="alert" className="text-xs font-medium text-destructive">
                {inline.error}
              </p>
            ) : null}
            {/* Side by side, never wrapped apart: cancel then confirm, at the row's end. */}
            <div className="flex items-center justify-end gap-2">
              <Button type="button" variant="ghost" size="xs" onClick={inline.cancel} disabled={inline.isSaving}>
                <X aria-hidden="true" />
                {inline.cancelLabel}
              </Button>
              <Button type="button" size="xs" onClick={inline.save} disabled={inline.isSaving}>
                {inline.isSaving ? (
                  <Loader2 className="animate-spin" aria-hidden="true" />
                ) : (
                  <Check aria-hidden="true" />
                )}
                {inline.confirmLabel}
              </Button>
            </div>
          </div>
        </EditorRow>
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
    <FieldFrame layout={layout} label={label} icon={icon}>
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
    </FieldFrame>
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
