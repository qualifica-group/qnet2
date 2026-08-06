import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { NoteComposer } from '@/features/notes/note-composer'
import { NoteItem } from '@/features/notes/note-item'
import type { Note } from '@/features/notes/types'

export interface NoteListProps {
  /** Spec 0085: etichetta di contesto su ogni root — solo nella vista aggregata. */
  showQuoteBadge?: boolean
  /** Root notes in server order (`created_at DESC`, D-13) — never re-sorted here. */
  roots: Note[]
  entityType: string
  entityId: number
  hasNextPage: boolean
  isFetchingNextPage: boolean
  onLoadMore: () => void
  /** Fired quando una reply viene creata o una nota eliminata (conteggio host). */
  onThreadChanged?: () => void
}

/**
 * Renders the thread: each root, its replies indented one level (D-7 — no
 * recursive nesting), and a "load more" control for the next keyset page.
 * Owns which root currently shows its inline reply composer; edit-in-place is
 * owned by `NoteItem` itself.
 */
export function NoteList({ roots, entityType, entityId, hasNextPage, isFetchingNextPage, onLoadMore, onThreadChanged, showQuoteBadge = false }: NoteListProps) {
  const { t } = useTranslation()
  const [replyingRootId, setReplyingRootId] = useState<number | null>(null)

  return (
    // Recessed thread surface. `bg-accent` (84% light) rather than `bg-muted`
    // (88%): the tray must sink below BOTH hosts — the white `FormSection` in
    // page and the `--background` dialog (91%) — and below the white bubbles,
    // and muted sits too close to the dialog surface to do that.
    <div className="flex flex-col gap-3 rounded-lg border border-muted-foreground/15 bg-accent p-2.5 dark:bg-card">
      {roots.map((root) => (
        <div key={root.id} className="flex flex-col gap-2">
          <NoteItem
            note={root}
            entityType={entityType}
            entityId={entityId}
            isRoot
            isReplying={replyingRootId === root.id}
            onToggleReply={() =>
              setReplyingRootId((current) => (current === root.id ? null : root.id))
            }
            onThreadChanged={onThreadChanged}
            showQuoteBadge={showQuoteBadge}
          />
          {(root.replies ?? []).length > 0 ? (
            <div className="ml-4 flex flex-col gap-2 border-l-2 border-muted-foreground/20 pl-3">
              <span className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                {t('notes.list.replyCount', {
                  defaultValue: 'risposte',
                  count: (root.replies ?? []).length,
                })}
              </span>
              {(root.replies ?? []).map((reply) => (
                <NoteItem
                  key={reply.id}
                  note={reply}
                  entityType={entityType}
                  entityId={entityId}
                  isRoot={false}
                  isReplying={false}
                  onThreadChanged={onThreadChanged}
                />
              ))}
            </div>
          ) : null}
          {replyingRootId === root.id ? (
            <div className="ml-4 border-l-2 border-muted-foreground/35 pl-3">
              <NoteComposer
                entityType={entityType}
                entityId={entityId}
                parentId={root.id}
                onDone={() => {
                  setReplyingRootId(null)
                  onThreadChanged?.()
                }}
                onCancel={() => setReplyingRootId(null)}
                autoFocus
              />
            </div>
          ) : null}
        </div>
      ))}
      {hasNextPage ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onLoadMore}
          disabled={isFetchingNextPage}
          className="self-start"
        >
          {isFetchingNextPage ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
          {t('notes.list.loadMore', { defaultValue: 'Carica altre' })}
        </Button>
      ) : null}
    </div>
  )
}
