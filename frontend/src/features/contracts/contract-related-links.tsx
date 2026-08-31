import { useTranslation } from 'react-i18next'
import { FileText, Handshake } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'
import type { ContractDetail } from '@/features/contracts/types'

interface ContractRelatedLinksProps {
  contract: ContractDetail
}

/**
 * `useModuleOpener` speaks the grid's `TableRow` (it is the type every other
 * call site hands it) but only ever reads `id` off it: the detail screen is
 * mounted by id and fetches its own data. The empty `actions` satisfies the
 * shape without pretending this row came from the grid.
 */
function asRow(id: number): TableRow {
  return { id, actions: [] }
}

/**
 * "Visualizza preventivo" / "Apri opportunità": aprono il record in una
 * MODALE, mai in un'altra pagina (direttiva utente 2026-08-31) — il
 * contratto che si sta guardando non va mai abbandonato. `forceMode:
 * 'modal'` e' esattamente il caso d'uso documentato da `useModuleOpener`
 * (spec 0067 D-3): ignora sia il `defaultMode` del modulo di destinazione
 * sia la preferenza di apertura dell'utente.
 *
 * Non sono gated sul ciclo di vita del contratto: restano disponibili anche
 * su un contratto chiuso, a differenza delle azioni di dominio.
 */
export function ContractRelatedLinks({ contract }: ContractRelatedLinksProps) {
  const { t } = useTranslation()
  const opportunity = contract.opportunity
  const quoteOpener = useModuleOpener('quotes', { forceMode: OPEN_MODE_MODAL })
  const opportunityOpener = useModuleOpener('opportunities', { forceMode: OPEN_MODE_MODAL })

  return (
    <>
      <Can permission="quotes.view">
        <Button
          type="button"
          variant="outline"
          className="bg-card"
          size="sm"
          onClick={() => quoteOpener.openView(asRow(contract.quote_id))}
        >
          <FileText aria-hidden="true" />
          {t('contracts.actions.viewQuote')}
        </Button>
      </Can>

      {opportunity ? (
        <Can permission="opportunities.view">
          <Button
            type="button"
            variant="outline"
            className="bg-card"
            size="sm"
            onClick={() => opportunityOpener.openView(asRow(opportunity.id))}
          >
            <Handshake aria-hidden="true" />
            {t('contracts.actions.openOpportunity')}
          </Button>
        </Can>
      ) : null}

      {quoteOpener.sheet}
      {opportunityOpener.sheet}
    </>
  )
}
