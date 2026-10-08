import { useEffect, useRef, type KeyboardEvent, type MouseEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, type FieldError, type FieldErrors } from 'react-hook-form'
import { Check, Loader2, Pencil, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { RecordField } from '@/components/detail/record-panel'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { cn } from '@/lib/utils'
import { useOutsidePointerDismiss } from '@/hooks/use-outside-pointer-dismiss'

/** The single-open-editor state and actions every inline row of a record (detail or create draft) shares. */
export interface InlineEdit {
  /** The form field whose editor is open, `null` when the record only displays. */
  editingField: string | null
  start: (field: string) => void
  cancel: () => void
  save: () => void
  /** A press outside the open row: Cancel on a detail (nothing saved), "Fatto" on a create draft (nothing lost). */
  dismiss: () => void
  isSaving: boolean
  /** A refused save the open editor's own field message cannot carry (generic server error). */
  error: string | null
  /** The open editor's confirm button: "Salva" on a detail, "Fatto" on a create draft. */
  confirmLabel: string
  /** Its cancel button: "Annulla" on a detail, "Ripristina" on a create draft (whose own Annulla leaves the form). */
  cancelLabel: string
}

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
type RecordInlineFieldLayout = 'row' | 'block'

interface FieldFrameProps {
  layout: RecordInlineFieldLayout
  label: string
  icon?: ReactNode
  className?: string
  children: ReactNode
}

function FieldFrame({ layout, label, icon, className, children }: FieldFrameProps) {
  if (layout === 'block') {
    return (
      <div className={cn('flex min-w-0 flex-col py-1', className)}>
        <span className="sr-only">{label}</span>
        {children}
      </div>
    )
  }
  return (
    <RecordField label={label} icon={icon} className={className}>
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

interface RecordInlineFieldProps {
  /** The form field this row edits: keys the single open editor and its error slot. */
  field: string
  /** The field permission gating the row, when it is not `field` itself (an Attribute row: `attribute_values`). */
  metaKey?: string
  label: string
  icon?: ReactNode
  inline: InlineEdit
  /** The control shown while editing: the SAME field component the create form uses (spec 0195 D-3). */
  editor: ReactNode
  /** Extra condition on top of the field permission (e.g. the referent needs an anagrafica). */
  canEdit?: boolean
  /** Defaults to `row`; see `RecordInlineFieldLayout`. */
  layout?: RecordInlineFieldLayout
  /** On the row's outer box, open or closed (e.g. a column span inside a grid). */
  className?: string
  /** The persisted value, as the read-only detail always rendered it. */
  children: ReactNode
}

/**
 * One row of a record detail that edits in place (spec 0195 D-2): the
 * persisted value as before, a pencil (on hover with a mouse, always on
 * touch) — or a click on the value itself — opens the field's own control
 * with Confirm/Cancel. Confirm PATCHes that field alone; Cancel or Esc
 * restores it, and a press outside the row closes it (`inline.dismiss`).
 * Hidden fields render nothing, non-editable ones no affordance (D-6),
 * exactly as `MetaField` would decide.
 */
export function RecordInlineField({
  field,
  metaKey = field,
  label,
  icon,
  inline,
  editor,
  canEdit = true,
  layout = 'row',
  className,
  children,
}: RecordInlineFieldProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { getFieldState, formState } = useFormContext()
  const editorRef = useRef<HTMLDivElement>(null)
  const permission = fieldPermission(metaKey)
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
        className={cn(EDITING_ROW_CLASS, className)}
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
    <FieldFrame layout={layout} label={label} icon={icon} className={className}>
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
            aria-label={t('common.inlineEdit.edit', { field: label })}
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
