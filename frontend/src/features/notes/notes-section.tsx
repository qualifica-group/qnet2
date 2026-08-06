import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { MessagesSquare } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { FormSection } from '@/components/form-section'
import { NoteComposer } from '@/features/notes/note-composer'
import { NoteQuoteScopeSelect } from '@/features/notes/note-quote-scope-select'
import type { NoteQuoteRef, NoteQuoteScope } from '@/features/notes/types'
import { NoteList } from '@/features/notes/note-list'
import { useNotes } from '@/features/notes/use-notes'

/** Hoistato: un `[]` inline creerebbe un riferimento nuovo a ogni render. */
const NO_QUOTES: NoteQuoteRef[] = []

interface NotesSectionProps {
  /** Domain slug registered in `config/notes.php` (D-9), owned entirely by the host module. */
  entityType: string
  /** Id of the host record within `entityType`. */
  entityId: number
  /**
   * Renders the built-in `FormSection` card (icon/title/description).
   * Default `true` keeps the panel's current look; pass `false` when an
   * ancestor already renders its own heading (e.g. `NotesDialog`'s
   * `DialogTitle`), mirroring `ContactsManager`'s `showHeader` convention.
   */
  showHeader?: boolean
  /**
   * Blocca la sezione su UNA Offerta (dettaglio Offerta): niente filtro, e ogni
   * nota scritta appartiene a quella. `null` = vista Opportunita', filtrabile.
   */
  lockedQuoteId?: number | null
}

const SKELETON_ROWS = 3

/**
 * Sole public component of the agnostic notes feature (D-14): a composer for
 * new root notes on top and the thread below, optionally wrapped in a titled
 * `FormSection` card. Carries no knowledge of the host module beyond the two
 * entity props (D-9) — this feature never imports from a host module's own
 * `features/` folder.
 */
export function NotesSection({
  entityType,
  entityId,
  showHeader = true,
  lockedQuoteId = null,
}: NotesSectionProps) {
  const { t } = useTranslation()
  // Spec 0085: lo scope e' stato LOCALE della sezione, non del server — cambiare
  // filtro e' una scelta di lettura, non una modifica del record.
  const [quoteScope, setQuoteScope] = useState<NoteQuoteScope>(lockedQuoteId ?? 'all')
  const {
    data,
    isLoading,
    isError,
    refetch,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useNotes(entityType, entityId, true, quoteScope)

  const roots = data?.pages.flatMap((page) => page.data) ?? []
  // Spec 0085 amendment: le Offerte arrivano con il thread, non dall'host —
  // cosi' ogni superficie che monta la sezione (tab del dettaglio, dialog di
  // riga, pannello di lavorazione) ha filtro e destinazione, non solo quelle
  // che hanno gia' caricato il record ospite. Si leggono dalla PRIMA pagina:
  // le successive descrivono lo stesso record, non un elenco diverso.
  const quotes = data?.pages[0]?.meta.quotes ?? NO_QUOTES

  const content = (
    <>
      {lockedQuoteId === null ? (
        <NoteQuoteScopeSelect
          value={quoteScope}
          onChange={setQuoteScope}
          quotes={quotes}
          label={t('notes.scope.filterLabel')}
          includeAll
          className="h-8 w-auto min-w-40 self-end text-xs"
        />
      ) : null}

      <NoteComposer
        entityType={entityType}
        entityId={entityId}
        quotes={quotes}
        // Il filtro attivo preseleziona la destinazione: se sto leggendo le note
        // di un'Offerta, la nota che scrivo appartiene quasi certamente a quella.
        defaultQuoteId={lockedQuoteId ?? (typeof quoteScope === 'number' ? quoteScope : null)}
        lockQuote={lockedQuoteId !== null}
      />

      {isLoading ? (
        <div className="flex flex-col gap-3">
          {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
            <div key={index} className="flex gap-2.5">
              <Skeleton className="size-8 shrink-0 rounded-full" />
              <Skeleton className="h-14 flex-1 rounded-lg" />
            </div>
          ))}
        </div>
      ) : isError ? (
        <div className="flex flex-col items-start gap-2">
          <p className="text-xs text-destructive">
            {t('notes.section.loadError', { defaultValue: 'Impossibile caricare le note.' })}
          </p>
          <Button type="button" variant="outline" size="sm" onClick={() => refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : roots.length === 0 ? (
        <div className="flex flex-col items-center gap-1.5 rounded-lg border border-dashed border-muted-foreground/25 px-4 py-6 text-center">
          <MessagesSquare className="size-5 text-muted-foreground/70" aria-hidden="true" />
          <p className="text-xs text-muted-foreground">
            {t('notes.section.empty', { defaultValue: 'Nessuna nota. Scrivi la prima per iniziare la discussione.' })}
          </p>
        </div>
      ) : (
        <NoteList
          roots={roots}
          entityType={entityType}
          entityId={entityId}
          hasNextPage={hasNextPage}
          isFetchingNextPage={isFetchingNextPage}
          onLoadMore={() => fetchNextPage()}
          // Solo quando il contesto NON e' gia' dato: con un filtro attivo o
          // sul dettaglio Offerta, l'etichetta ripeterebbe cio' che si sa gia'.
          showQuoteBadge={lockedQuoteId === null && quoteScope === 'all' && quotes.length > 0}
        />
      )}
    </>
  )

  if (!showHeader) {
    return <div className="flex flex-col gap-4">{content}</div>
  }

  return (
    <FormSection
      icon={MessagesSquare}
      title={t('notes.section.title', { defaultValue: 'Note' })}
      description={t('notes.section.description', {
        defaultValue: 'Discuti il record con i colleghi: usa @ per menzionarli.',
      })}
    >
      {content}
    </FormSection>
  )
}
