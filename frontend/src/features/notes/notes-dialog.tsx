import { useTranslation } from 'react-i18next'
import { MessagesSquare } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { NotesSection } from '@/features/notes/notes-section'

export interface NotesDialogProps {
  /** Domain slug registered in `config/notes.php` (D-9), owned entirely by the host module. */
  entityType: string
  /** Id of the host record within `entityType`; `null` closes the dialog. */
  entityId: number | null
  onOpenChange: (open: boolean) => void
  /** Optional record label (e.g. its name); falls back to the generic "Note" title. */
  title?: string
  /**
   * Spec 0085: blocca il thread su UNA Offerta del record ospite — la lista
   * mostra solo le sue note e il composer le crea con quel `quote_id`, senza
   * selettori. `null` = thread completo dell'Opportunita', filtrabile.
   */
  lockedQuoteId?: number | null
  /**
   * Inoltrata a `NotesSection`: l'host la usa per rinfrescare il proprio
   * conteggio (badge `notes_count` della riga) appena una nota viene creata o
   * eliminata, senza aspettare la chiusura del dialog.
   */
  onThreadChanged?: () => void
}

/**
 * Row-action Dialog mounting the agnostic `NotesSection` for a single host
 * record, so a table row can open the thread without navigating into the
 * record itself. Mirrors `DocumentsDialog`'s shape exactly (open state driven
 * by the id prop, same sizing/scroll strategy) — the twin dialog already in
 * the project for the same row-action pattern.
 *
 * `NotesSection`'s own `FormSection` header is suppressed (`showHeader=false`)
 * so the dialog doesn't show a "Note" title twice: this dialog's own
 * `DialogTitle` carries it instead.
 */
export function NotesDialog({
  entityType,
  entityId,
  onOpenChange,
  title,
  lockedQuoteId = null,
  onThreadChanged,
}: NotesDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={entityId !== null} onOpenChange={onOpenChange}>
      <DialogContent size="lg" className="gap-0 p-0">
        <DialogHeader className="flex-row items-center gap-3 space-y-0 rounded-t-lg border-b bg-muted/40 px-5 py-4 text-left">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <MessagesSquare className="size-4.5" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <DialogTitle className="text-base">
              {title ?? t('notes.section.title', { defaultValue: 'Note' })}
            </DialogTitle>
            <DialogDescription className="text-xs">
              {t('notes.section.description', {
                defaultValue: 'Discuti il record con i colleghi: usa @ per menzionarli.',
              })}
            </DialogDescription>
          </div>
        </DialogHeader>
        {entityId !== null ? (
          <div className="max-h-[70vh] overflow-y-auto rounded-b-lg px-5 py-4">
            <NotesSection
              entityType={entityType}
              entityId={entityId}
              showHeader={false}
              lockedQuoteId={lockedQuoteId}
              onThreadChanged={onThreadChanged}
            />
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}
