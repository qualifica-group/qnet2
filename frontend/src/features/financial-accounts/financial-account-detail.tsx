import { useTranslation } from 'react-i18next'
import { Landmark } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { FinancialAccountCardNumber } from '@/features/financial-accounts/financial-account-card-number'
import type { FinancialAccountDetailWithPermissions } from '@/features/financial-accounts/types'

interface FinancialAccountDetailViewProps {
  financialAccount: FinancialAccountDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/** Read-only detail of a financial account; shows only the fields of its type. */
export function FinancialAccountDetailView({
  financialAccount: account,
  onEdit,
}: FinancialAccountDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(account.created_at)
  const updatedAt = formatDateTime(account.updated_at)
  const { permissions } = account
  const canEdit = permissions.resource.update
  const canViewActivity = permissions.actions.view_activity
  const canReveal = permissions.actions.reveal_card_number === true

  const isBank = account.type === 'bank_account'
  const isCard = account.type === 'card'
  const address = [account.address_line, account.postal_code, account.city?.name, account.province?.name]
    .filter(Boolean)
    .join(', ')

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('financial-accounts', account.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={account.name} icon={<Landmark />} />}
            title={account.name}
            subtitle={t(`financialAccounts.types.${account.type}`)}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('financialAccounts.form.sections.details.title')} full>
              <RecordFieldList>
                <RecordField label={t('financialAccounts.detail.company')}>
                  {account.company ? account.company.name : <DetailEmpty />}
                </RecordField>
                {isBank ? (
                  <>
                    <RecordField label={t('financialAccounts.detail.iban')}>{account.iban}</RecordField>
                    <RecordField label={t('financialAccounts.detail.accountNumber')}>
                      {account.account_number}
                    </RecordField>
                  </>
                ) : null}
                {isCard ? (
                  <>
                    <RecordField label={t('financialAccounts.detail.cardType')}>
                      {account.card_type ? t(`financialAccounts.cardTypes.${account.card_type}`) : <DetailEmpty />}
                    </RecordField>
                    <RecordField label={t('financialAccounts.detail.cardCircuit')}>
                      {account.card_circuit ? t(`financialAccounts.circuits.${account.card_circuit}`) : <DetailEmpty />}
                    </RecordField>
                    <RecordField label={t('financialAccounts.detail.linkedAccount')}>
                      {account.linked_account ? account.linked_account.name : <DetailEmpty />}
                    </RecordField>
                    <RecordField label={t('financialAccounts.detail.cardHolder')}>
                      {account.card_holder}
                    </RecordField>
                    <RecordField label={t('financialAccounts.detail.cardNumber')}>
                      <FinancialAccountCardNumber
                        accountId={account.id}
                        masked={account.card_number_masked}
                        canReveal={canReveal}
                      />
                    </RecordField>
                    <RecordField label={t('financialAccounts.detail.cardExpiry')}>
                      {account.card_expiry}
                    </RecordField>
                  </>
                ) : null}
                {isCard ? null : (
                  <RecordField label={t('financialAccounts.detail.address')}>
                    {address ? address : <DetailEmpty />}
                  </RecordField>
                )}
                <RecordField label={t('financialAccounts.detail.notes')}>
                  {account.notes ? account.notes : <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('financialAccounts.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('financialAccounts.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
