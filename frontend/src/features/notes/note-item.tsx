import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { formatDateTime } from '@/lib/formatting/date-display'
import { useConfirm } from '@/components/confirm-dialog-context'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { NoteBody } from '@/features/notes/note-body'
import { NoteComposer } from '@/features/notes/note-composer'
import { useDeleteNote } from '@/features/notes/use-note-mutations'
import type { Note } from '@/features/notes/types'

export interface NoteItemProps {
  note: Note
  entityType: string
  entityId: number
  /** True for a thread root; replies never show the "reply" action (D-7). */
  isRoot: boolean
  /** True while this root's inline reply composer is open. */
  isReplying: boolean
  /** Toggles the inline reply composer. Omitted for replies. */
  onToggleReply?: () => void
  /**
   * Spec 0085: mostra l'etichetta di contesto (codice Offerta o "Generale").
   * Vera solo nella vista AGGREGATA: con un filtro attivo, o sul dettaglio
   * Offerta, il contesto e' gia' dato e ripeterlo su ogni riga e' rumore.
   */
  showQuoteBadge?: boolean
}

/**
 * One note row: avatar + author + timestamp, body with mention chips, and the
 * author-only edit/delete actions gated by `note.can` (AC-071, D-8). Editing
 * swaps the row for an inline `NoteComposer`; deleting asks for confirmation
 * via the app-wide `useConfirm` (requires `ConfirmDialogProvider`, already
 * mounted at the app root in `App.tsx`).
 */
export function NoteItem({
  note,
  entityType,
  entityId,
  isRoot,
  isReplying,
  onToggleReply,
  showQuoteBadge = false,
}: NoteItemProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const deleteNote = useDeleteNote(entityType, entityId)
  const [isEditing, setIsEditing] = useState(false)

  const handleDelete = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('notes.item.deleteAction', { defaultValue: 'Elimina nota' }),
      description: t('notes.item.deleteConfirm', {
        defaultValue: "La nota sparira' dall'elenco. Le eventuali risposte restano nascoste insieme ad essa.",
      }),
      confirmLabel: t('notes.item.deleteAction', { defaultValue: 'Elimina nota' }),
    })
    if (!confirmed) {
      return
    }
    await deleteNote.mutateAsync(note.id)
  }

  if (isEditing) {
    return (
      <NoteComposer
        entityType={entityType}
        entityId={entityId}
        editingNote={note}
        onDone={() => setIsEditing(false)}
        onCancel={() => setIsEditing(false)}
        autoFocus
      />
    )
  }

  return (
    <div className="group/note flex gap-2.5">
      <UserAvatar
        name={note.author.name}
        src={note.author.avatar_url}
        size="sm"
        className="mt-0.5 shrink-0 ring-2 ring-background"
      />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <div
          className={cn(
            // White bubbles (dark mode: a lighter surface than the card behind them),
            // so a note always reads as a raised sheet over the section background.
            'rounded-lg rounded-tl-sm border px-3 py-2 transition-colors',
            isRoot
              ? 'border-muted-foreground/15 bg-white shadow-xs group-hover/note:border-muted-foreground/25 dark:bg-muted/50'
              : 'border-muted-foreground/10 bg-white/80 dark:bg-muted/30',
          )}
        >
          <div className="mb-0.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span className="text-sm font-semibold text-foreground">{note.author.name}</span>
            <span className="text-[11px] text-muted-foreground">
              {formatDateTime(note.created_at)}
            </span>
            {note.edited_at ? (
              <span className="rounded-sm bg-muted px-1 py-px text-[10px] text-muted-foreground">
                {t('notes.item.edited', { defaultValue: '(modificato)' })}
              </span>
            ) : null}
            {/* Spec 0085: il contesto e' sempre visibile nella vista aggregata,
                cosi' non si confonde una nota di offerta con una generale. Non
                sulle reply: ereditano quello della root, ripeterlo sarebbe
                rumore su ogni riga del thread. */}
            {showQuoteBadge ? (
              <span className="rounded-sm bg-muted px-1 py-px text-[10px] text-muted-foreground">
                {note.quote ? note.quote.code : t('notes.scope.generalBadge')}
              </span>
            ) : null}
          </div>
          <NoteBody body={note.body} />
        </div>
        <div className="flex items-center gap-0.5 opacity-60 transition-opacity focus-within:opacity-100 group-hover/note:opacity-100">
          {isRoot && onToggleReply ? (
            <Button type="button" variant="ghost" size="xs" onClick={onToggleReply}>
              {isReplying ? t('common.cancel') : t('notes.item.replyAction', { defaultValue: 'Rispondi' })}
            </Button>
          ) : null}
          {note.can.update ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              aria-label={t('notes.item.editAction', { defaultValue: 'Modifica nota' })}
              onClick={() => setIsEditing(true)}
            >
              <Pencil aria-hidden="true" />
            </Button>
          ) : null}
          {note.can.delete ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              aria-label={t('notes.item.deleteAction', { defaultValue: 'Elimina nota' })}
              onClick={handleDelete}
              disabled={deleteNote.isPending}
            >
              {deleteNote.isPending ? (
                <Loader2 className="size-3 animate-spin" aria-hidden="true" />
              ) : (
                <Trash2 aria-hidden="true" />
              )}
            </Button>
          ) : null}
        </div>
      </div>
    </div>
  )
}
