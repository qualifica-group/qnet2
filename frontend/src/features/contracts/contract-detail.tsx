import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { History, Paperclip } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CONTRACT_ATTACHABLE_ALIAS, CONTRACTS_DOMAIN } from '@/features/contracts/api'
import { ContractActionsBar } from '@/features/contracts/contract-actions-bar'
import { ContractDetailHeader } from '@/features/contracts/contract-detail-header'
import { ContractDetailSections } from '@/features/contracts/contract-detail-fields'
import {
  ContractWorkOrdersSection,
  type ContractWorkOrdersSectionHandle,
} from '@/features/contracts/contract-work-orders-section'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

const CONTRACT_DOCUMENTS_TAB = 'contract-documents'
const OPPORTUNITY_DOCUMENTS_TAB = 'opportunity-documents'
const ACTIVITY_TAB = 'activity'

/** Compact trigger sizing of the collaboration strip, the same the offer record uses. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

/**
 * Two-column body, the same rule the Offerta and the Opportunita' records
 * follow. Each column is its OWN `@container` so the section/field grids inside
 * break on the COLUMN's width, not the canvas'.
 */
const BODY_GRID_CLASS =
  'grid grid-cols-1 items-start gap-4 @5xl:grid-cols-[minmax(0,7fr)_minmax(0,4fr)]'
const COLUMN_CLASS = '@container flex min-w-0 flex-col gap-4'

interface ContractDetailViewProps {
  contract: ContractDetailWithPermissions
}

/**
 * The contract's collaboration card: Documenti contratto | Documenti
 * opportunità | Attività — the offer's card with the surfaces a contract
 * actually has (it owns no notes thread of its own).
 *
 * The contract's own documents honour the actor's attachment abilities; the
 * opportunity's are ALWAYS read-only (AC-047): they belong to another record,
 * this screen only shows them. Attività is gated on its own action flag.
 */
function ContractDetailCollaboration({ contract }: { contract: ContractDetailWithPermissions }) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canViewActivity = contract.permissions.actions.view_activity

  return (
    <RecordCard>
      <Tabs defaultValue={CONTRACT_DOCUMENTS_TAB} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            <TabsTrigger value={CONTRACT_DOCUMENTS_TAB} className={TRIGGER_CLASS}>
              <Paperclip className="size-3.5" aria-hidden="true" />
              {t('contracts.detail.tabs.contractDocuments')}
            </TabsTrigger>
            <TabsTrigger value={OPPORTUNITY_DOCUMENTS_TAB} className={TRIGGER_CLASS}>
              <Paperclip className="size-3.5" aria-hidden="true" />
              {t('contracts.detail.tabs.opportunityDocuments')}
            </TabsTrigger>
            {canViewActivity ? (
              <TabsTrigger value={ACTIVITY_TAB} className={TRIGGER_CLASS}>
                <History className="size-3.5" aria-hidden="true" />
                {t('activityLog.title')}
              </TabsTrigger>
            ) : null}
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          <TabsContent value={CONTRACT_DOCUMENTS_TAB}>
            <DocumentsSection
              resource={CONTRACT_ATTACHABLE_ALIAS}
              id={contract.id}
              canUpload={can('attachments.create')}
              canDelete={can('attachments.delete')}
            />
          </TabsContent>

          <TabsContent value={OPPORTUNITY_DOCUMENTS_TAB}>
            {contract.opportunity ? (
              <DocumentsSection
                resource={OPPORTUNITY_ATTACHABLE_ALIAS}
                id={contract.opportunity.id}
                canUpload={false}
                canDelete={false}
              />
            ) : (
              <p className="text-xs text-muted-foreground">{t('contracts.detail.noOpportunity')}</p>
            )}
          </TabsContent>

          {canViewActivity ? (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource={CONTRACTS_DOMAIN} id={contract.id} />
            </TabsContent>
          ) : null}
        </div>
      </Tabs>
    </RecordCard>
  )
}

/**
 * Read-only detail of a single contract (spec 0072, AC-043), rendered on the
 * same `RecordCanvas` kit as `/quotes/:id` (user directive 2026-08-31): on the
 * left ONE card carrying identity + domain actions + titled sections and, as
 * its closing band, the read-only product lines; the collaboration card (documenti, attività) on
 * the right; a metadata footer below.
 *
 * Every field is a live projection through the quote (BR-7/AC-040, nothing is
 * duplicated on `contracts`); the domain actions
 * (validate/schedule/terminate/reactivate/edit/change-status) live in
 * `ContractActionsBar`, each gated on its own permission and on the lifecycle
 * (AC-044).
 */
export function ContractDetailView({ contract: initialContract }: ContractDetailViewProps) {
  const { t } = useTranslation()
  const [contract, setContract] = useState(initialContract)
  const createdAt = formatDateTime(contract.created_at)
  const updatedAt = formatDateTime(contract.updated_at)

  // "Programma" lives in the actions bar (a SIBLING of the Commesse tab
  // below), so its success is bubbled here and forwarded through this ref
  // rather than lifting the tab's own grid state (AC-062).
  const workOrdersRef = useRef<ContractWorkOrdersSectionHandle>(null)

  return (
    <ResourcePermissionsProvider permissions={contract.permissions}>
      <RecordCanvas>
        <div className={BODY_GRID_CLASS}>
          <div className={COLUMN_CLASS}>
            <RecordCard>
              <ContractDetailHeader contract={contract} />
              <ContractActionsBar
                contract={contract}
                onChanged={setContract}
                onWorkOrderCreated={() => workOrdersRef.current?.refresh()}
              />
              <ContractDetailSections contract={contract} />

              {/* Banda di chiusura: le sole righe di ricavo del preventivo
                  (BR-7), read-only. Nessuna strip (qui c'e' una sola collezione,
                  non due come sull'Offerta) e nessun riepilogo economico —
                  ricavi/costi/margine attesi sono usciti dalla scheda per
                  direttiva utente 2026-08-31. */}
              <div className="min-w-0 border-t p-4">
                <QuoteLinesReadOnlyList lines={contract.offer_lines} />
              </div>
            </RecordCard>
          </div>

          <div className={COLUMN_CLASS}>
            <ContractDetailCollaboration contract={contract} />
          </div>
        </div>

        {/*
          Tab "Commesse" (spec 0095 D-8/D-10): a piena larghezza sotto la
          griglia a due colonne, non nella card di collaborazione — quella
          colonna (`minmax(0,4fr)`) e' troppo stretta per una TableView con
          toolbar + griglia + filtri.
        */}
        <ContractWorkOrdersSection ref={workOrdersRef} quoteId={contract.quote_id} />

        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('contracts.detail.createdAt')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('contracts.detail.updatedAt')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      </RecordCanvas>
    </ResourcePermissionsProvider>
  )
}
