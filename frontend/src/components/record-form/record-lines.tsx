import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { Button } from '@/components/ui/button'

/**
 * A record's lines table edited in place (spec 0263): the line open in the
 * editor replaces its row, the other rows keep their actions column.
 */
export interface LinesTableEditing {
  /** Index of the open line (a saved one, or `lines.length` for a new line); null when none is open. */
  index: number | null
  /** The open line's editor. */
  row: ReactNode
  /** Pencil/trash of a saved row; absent when the record is read-only. */
  actions?: (index: number) => ReactNode
}

/** The open line's editor across the whole table width (the editor row renders an `li`). */
export function LineEditorTableRow({ colSpan, children }: { colSpan: number; children: ReactNode }) {
  return (
    <tr className="border-b border-border last:border-b-0">
      <td colSpan={colSpan} className="p-1.5">
        <ul>{children}</ul>
      </td>
    </tr>
  )
}

interface LineRowActionsProps {
  /** 1-based line number, in the accessible names. */
  n: number
  onEdit?: () => void
  onRemove?: () => void
}

/** Icon-only pencil and trash of a saved line. */
export function LineRowActions({ n, onEdit, onRemove }: LineRowActionsProps) {
  const { t } = useTranslation()
  return (
    <div className="flex justify-end gap-1">
      {onEdit ? (
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          aria-label={t('common.inlineEdit.lines.edit', { n })}
          onClick={onEdit}
        >
          <Pencil />
        </Button>
      ) : null}
      {onRemove ? (
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="text-destructive"
          aria-label={t('common.inlineEdit.lines.remove', { n })}
          onClick={onRemove}
        >
          <Trash2 />
        </Button>
      ) : null}
    </div>
  )
}

interface RecordLinesSectionProps {
  title: string
  /** The lines can change: "Aggiungi riga" shows. */
  canWrite: boolean
  inline: InlineEdit
  /** The add picker (catalog / free text); `close` hides it once a line was chosen. */
  addPanel: (close: () => void) => ReactNode
  children: ReactNode
}

/** "Righe" of a record: the title with "Aggiungi riga", the lines table and, once asked, the add picker. */
export function RecordLinesSection({ title, canWrite, inline, addPanel, children }: RecordLinesSectionProps) {
  const { t } = useTranslation()
  const [adding, setAdding] = useState(false)

  return (
    <section className="flex flex-col gap-1.5">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-semibold text-foreground">{title}</h3>
        {canWrite ? (
          <Button
            type="button"
            variant="secondary"
            size="xs"
            disabled={inline.isSaving}
            onClick={() => setAdding((open) => !open)}
          >
            <Plus aria-hidden="true" />
            {t('common.inlineEdit.lines.add')}
          </Button>
        ) : null}
      </div>
      {children}
      {canWrite && adding ? addPanel(() => setAdding(false)) : null}
    </section>
  )
}
