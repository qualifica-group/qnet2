import { useTranslation } from 'react-i18next'
import { Building, Building2, CreditCard, Handshake, MapPin, UserRound } from 'lucide-react'
import { DetailEmpty, DetailField, DetailGrid, DetailSection } from '@/components/detail/detail-panel'
import type { ContractDetail } from '@/features/contracts/types'

/** Formats a `date` (no time part) field the same way the grid's `DateCell` does, off the active UI locale. */
function formatDate(value: string | null, language: string): string | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return null
  }
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium' }).format(date)
}

/** Identity/relations section: the same relations the quote projects, never duplicated on `contracts` (AC-040). */
export function ContractIdentitySection({ contract }: { contract: ContractDetail }) {
  const { t } = useTranslation()

  return (
    <DetailSection title={t('contracts.detail.sections.identity')}>
      <DetailGrid>
        <DetailField label={t('contracts.detail.registry')} icon={<Building2 />}>
          {contract.registry ? contract.registry.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.opportunity')} icon={<Handshake />}>
          {contract.opportunity ? contract.opportunity.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.company')}>
          {contract.company ? contract.company.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.companySite')}>
          {contract.company_site ? contract.company_site.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.operationalSite')} icon={<MapPin />}>
          {contract.operational_site ? contract.operational_site.label : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.commercial')} icon={<UserRound />}>
          {contract.commercial ? contract.commercial.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.reporter')} icon={<UserRound />}>
          {contract.reporter ? contract.reporter.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.supervisor')} icon={<UserRound />}>
          {contract.supervisor ? contract.supervisor.name : <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.paymentMethod')} icon={<Building />}>
          {contract.payment_method ? contract.payment_method.name : <DetailEmpty />}
        </DetailField>
      </DetailGrid>
    </DetailSection>
  )
}

/** Lifecycle dates section: quote date through termination, plus who validated/terminated it. */
export function ContractLifecycleSection({ contract }: { contract: ContractDetail }) {
  const { t, i18n } = useTranslation()

  return (
    <DetailSection title={t('contracts.detail.sections.lifecycle')}>
      <DetailGrid>
        <DetailField label={t('contracts.detail.quoteDate')}>
          {formatDate(contract.quote.created_at, i18n.language) ?? <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.acceptedAt')}>
          {formatDate(contract.accepted_at, i18n.language) ?? <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.validatedAt')}>
          {contract.validated_at ? (
            <>
              {formatDate(contract.validated_at, i18n.language)}
              {contract.validated_by ? (
                <span className="text-muted-foreground"> — {contract.validated_by.name}</span>
              ) : null}
            </>
          ) : (
            <DetailEmpty />
          )}
        </DetailField>
        <DetailField label={t('contracts.detail.renewalDate')}>
          {formatDate(contract.renewal_date, i18n.language) ?? <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.expiryDate')}>
          {formatDate(contract.expiry_date, i18n.language) ?? <DetailEmpty />}
        </DetailField>
        <DetailField label={t('contracts.detail.terminatedAt')}>
          {contract.terminated_at ? (
            <>
              {formatDate(contract.terminated_at, i18n.language)}
              {contract.terminated_by ? (
                <span className="text-muted-foreground"> — {contract.terminated_by.name}</span>
              ) : null}
            </>
          ) : (
            <DetailEmpty />
          )}
        </DetailField>
        {contract.termination_reason ? (
          <DetailField label={t('contracts.detail.terminationReason')} full>
            <span className="whitespace-pre-wrap">{contract.termination_reason}</span>
          </DetailField>
        ) : null}
      </DetailGrid>
    </DetailSection>
  )
}

/** Free-text section: payment notes and comments, both PATCH-editable via "Modifica dati". */
export function ContractNotesSection({ contract }: { contract: ContractDetail }) {
  const { t } = useTranslation()

  return (
    <DetailSection title={t('contracts.detail.sections.notes')} icon={<CreditCard />}>
      <DetailGrid>
        <DetailField label={t('contracts.detail.paymentNotes')} full>
          <span className="whitespace-pre-wrap">{contract.payment_notes ?? <DetailEmpty />}</span>
        </DetailField>
        <DetailField label={t('contracts.detail.comments')} full>
          <span className="whitespace-pre-wrap">{contract.comments ?? <DetailEmpty />}</span>
        </DetailField>
      </DetailGrid>
    </DetailSection>
  )
}
