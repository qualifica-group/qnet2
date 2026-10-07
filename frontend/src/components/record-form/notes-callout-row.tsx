import { useId, type ReactNode } from 'react'
import { StickyNote } from 'lucide-react'
import {
  GENERAL_NOTES_CALLOUT_CLASS,
  GENERAL_NOTES_TEXT_CLASS,
  GENERAL_NOTES_TITLE_CLASS,
} from '@/components/record-form/layout'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { cn } from '@/lib/utils'

interface NotesCalloutRowProps {
  /** The form field holding the note (`general_notes`, `internal_notes`): keys the inline editor and its permission. */
  field: string
  title: string
  notes: string | null
  /** What an empty, editable note invites the operator to write. */
  placeholder: string
  inline: InlineEdit
  /** The note's textarea, styled with `GENERAL_NOTES_TEXTAREA_CLASS`: it is written inside the callout. */
  editor: ReactNode
  className?: string
}

/**
 * A record's note (Opportunita' "Note generali", Commessa/Offerta "Note
 * interne") in the very callout the request work panel shows it in (user
 * directive 2026-10-07, "lo stesso stile come in gestione richieste"): the
 * amber box and its micro-title are always there, the text — or, with no
 * note, the placeholder — sits inside it, and the textarea opens INSIDE it
 * too, through the record's in-place edit (a click on the text or its pencil).
 *
 * A read-only record carrying no note shows nothing at all: there is nothing
 * to read and nothing to write.
 */
export function NotesCalloutRow({
  field,
  title,
  notes,
  placeholder,
  inline,
  editor,
  className,
}: NotesCalloutRowProps) {
  const titleId = useId()
  const { field: fieldPermission } = useResourcePermissions()
  const permission = fieldPermission(field)
  const hasNotes = notes !== null && notes.trim() !== ''
  const isEditing = inline.editingField === field

  if (!permission.visible || (!hasNotes && !isEditing && !permission.editable)) {
    return null
  }

  return (
    <section aria-labelledby={titleId} className={cn(GENERAL_NOTES_CALLOUT_CLASS, className)}>
      <h3 id={titleId} className={GENERAL_NOTES_TITLE_CLASS}>
        <StickyNote className="size-3.5 shrink-0" aria-hidden="true" />
        {title}
      </h3>
      <RecordInlineField field={field} label={title} inline={inline} layout="block" editor={editor} className="mt-1">
        {hasNotes ? (
          <p className={GENERAL_NOTES_TEXT_CLASS}>{notes}</p>
        ) : (
          <p className="text-sm text-muted-foreground">{placeholder}</p>
        )}
      </RecordInlineField>
    </section>
  )
}
