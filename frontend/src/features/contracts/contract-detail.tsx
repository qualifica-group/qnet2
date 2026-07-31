import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FileText, History, Package, Paperclip } from 'lucide-react'
import {
  DetailField,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import { formatDateTime } from '@/features/table/cell-renderers'
import { CONTRACTS_DOMAIN } from '@/features/contracts/api'
import { ContractActionsBar } from '@/features/contracts/contract-actions-bar'
import {
  ContractIdentitySection,
  ContractLifecycleSection,
  ContractNotesSection,
} from '@/features/contracts/contract-detail-fields'
import { ContractSummaryPanel } from '@/features/contracts/contract-summary-panel'
import {
  ContractAlertBadge,
  ContractStatusBadge,
  ContractSuspendedBadge,
} from '@/features/contracts/contract-status-badges'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

const PRODUCTS_TAB = 'products'
const CONTRACT_DOCUMENTS_TAB = 'contract-documents'
const OPPORTUNITY_DOCUMENTS_TAB = 'opportunity-documents'
const ACTIVITY_TAB = 'activity'

interface ContractDetailViewProps {
  contract: ContractDetailWithPermissions
}

/**
 * Read-only detail of a single contract (spec 0072, AC-043): the identity
 * relations, the lifecycle dates and the economic summary — all live
 * projections through the quote (BR-7/AC-040, nothing is duplicated on
 * `contracts`) — plus a tab strip for the read-only product lines and the
 * two documents surfaces (contract vs. opportunity, the latter always
 * read-only, AC-047). Domain actions (validate/schedule/terminate/reactivate/
 * edit) live in `ContractActionsBar`, each gated on its own permission
 * (AC-044). Mirrors `QuoteDetailView`'s shell, mixed with
 * `OpportunityDetailView`'s Documents/Activity tabs.
 */
export function ContractDetailView({ contract: initialContract }: ContractDetailViewProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [contract, setContract] = useState(initialContract)
  // Controlled, so the tab strip can hand the selection over to its select
  // fallback when the tabs no longer fit.
  const [activeTab, setActiveTab] = useState(PRODUCTS_TAB)

  const createdAt = formatDateTime(contract.created_at)
  const canViewActivity = contract.permissions.actions.view_activity

  return (
    <ResourcePermissionsProvider permissions={contract.permissions}>
      <DetailPanel>
        <DetailHero
          media={<DetailMonogram name={contract.quote.title} icon={<FileText />} />}
          title={contract.quote.title}
          subtitle={contract.quote.code}
          badges={
            <>
              <ContractStatusBadge status={contract.contract_status} />
              {contract.is_suspended ? (
                <ContractSuspendedBadge
                  suspendedAt={contract.suspended_at}
                  previousStatus={contract.status_before_suspension}
                />
              ) : null}
              <ContractAlertBadge alert={contract.alert} />
            </>
          }
        />

        <ContractActionsBar contract={contract} onChanged={setContract} />

        <ContractIdentitySection contract={contract} />
        <ContractLifecycleSection contract={contract} />
        <ContractNotesSection contract={contract} />

        <DetailSection title={t('contracts.detail.sections.summary')}>
          <ContractSummaryPanel summary={contract.summary} />
        </DetailSection>

        <DetailSection>
          <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col gap-3">
            <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
              <TabsTrigger value={PRODUCTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                <Package aria-hidden="true" />
                {t('contracts.detail.tabs.products')}
              </TabsTrigger>
              <TabsTrigger value={CONTRACT_DOCUMENTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                <Paperclip aria-hidden="true" />
                {t('contracts.detail.tabs.contractDocuments')}
              </TabsTrigger>
              <TabsTrigger value={OPPORTUNITY_DOCUMENTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                <Paperclip aria-hidden="true" />
                {t('contracts.detail.tabs.opportunityDocuments')}
              </TabsTrigger>
              {canViewActivity ? (
                <TabsTrigger value={ACTIVITY_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                  <History aria-hidden="true" />
                  {t('activityLog.title')}
                </TabsTrigger>
              ) : null}
            </FormTabStrip>

            <TabsContent value={PRODUCTS_TAB}>
              <QuoteLinesReadOnlyList lines={contract.offer_lines} />
            </TabsContent>

            <TabsContent value={CONTRACT_DOCUMENTS_TAB}>
              <DocumentsSection
                resource="contract"
                id={contract.id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </TabsContent>

            <TabsContent value={OPPORTUNITY_DOCUMENTS_TAB}>
              {contract.opportunity ? (
                <DocumentsSection
                  resource="opportunity"
                  id={contract.opportunity.id}
                  canUpload={false}
                  canDelete={false}
                />
              ) : (
                <DetailField label={t('contracts.detail.opportunity')}>
                  {t('contracts.detail.noOpportunity')}
                </DetailField>
              )}
            </TabsContent>

            {canViewActivity ? (
              <TabsContent value={ACTIVITY_TAB}>
                <ActivityLogSection resource={CONTRACTS_DOMAIN} id={contract.id} />
              </TabsContent>
            ) : null}
          </Tabs>
        </DetailSection>

        {createdAt ? <DetailMeta label={t('contracts.detail.createdAt')}>{createdAt}</DetailMeta> : null}
      </DetailPanel>
    </ResourcePermissionsProvider>
  )
}
