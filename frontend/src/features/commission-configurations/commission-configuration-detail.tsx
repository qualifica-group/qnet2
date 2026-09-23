import { CalendarRange, CircleDollarSign, Settings2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { DetailMonogram } from '@/components/detail/detail-panel'
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
import { RecordLink } from '@/components/detail/record-link'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CommissionConfigurationDetailWithPermissions, CommissionRecipientType } from './types'
import { formatDate } from '@/lib/formatting/date-display'

/** Placeholder for an open-ended validity bound. */
const EMPTY_DATE = '–'

/**
 * Record module of each recipient type. A `user` recipient has no entry: people
 * are not linked as records (see `RecordLink`), so it stays plain text.
 */
const RECIPIENT_DOMAINS: Partial<Record<CommissionRecipientType, string>> = {
  referent: 'referents',
  registry: 'registries',
}

interface CommissionConfigurationDetailViewProps {
  configuration: CommissionConfigurationDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single commission configuration, rendered as an
 * enterprise-CRM record on the same kit Opportunita' uses: the identity/
 * fields card on the left, the activity card on the right, a metadata
 * footer. Every field is additionally gated on its own per-field
 * `permissions.fields[key].visible` (spec 0004), on top of the record-level
 * Edit affordance gated on `permissions.resource.update`.
 */
export function CommissionConfigurationDetailView({
  configuration,
  onEdit,
}: CommissionConfigurationDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = configuration.permissions.resource.update
  const canViewActivity = configuration.permissions.actions.view_activity
  const visible = (field: string) =>
    configuration.permissions.fields[field]?.visible ?? true
  const scopeRelationField =
    configuration.application_scope === 'PRODUCT'
      ? 'product_id'
      : configuration.application_scope === 'PRODUCT_CATEGORY'
        ? 'product_category_id'
        : null
  const scopeSectionVisible =
    visible('recipient_role') ||
    visible('application_scope') ||
    (scopeRelationField ? visible(scopeRelationField) : false) ||
    visible('recipient_id')
  const calculationSectionVisible =
    visible('commission_type') ||
    visible('value') ||
    visible('priority') ||
    visible('status')
  const validitySectionVisible =
    visible('valid_from') ||
    visible('valid_until') ||
    visible('internal_note')
  const option = (field: string, value: string) =>
    t(`commissionConfigurations.options.${field}.${value}`)
  const recipientDomain = configuration.recipient_type
    ? RECIPIENT_DOMAINS[configuration.recipient_type]
    : undefined
  const formattedValue =
    configuration.commission_type === 'PERCENTAGE'
      ? `${new Intl.NumberFormat(i18n.language, { maximumFractionDigits: 4 }).format(Number(configuration.value))}%`
      : new Intl.NumberFormat(i18n.language, { minimumFractionDigits: 2 }).format(Number(configuration.value))
  const title = visible('name') ? configuration.name : t('commissionConfigurations.detail.title')

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('commission-configurations', configuration.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={title} icon={<CircleDollarSign />} />}
            title={title}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            {scopeSectionVisible ? (
              <RecordSection title={t('commissionConfigurations.detail.scope')} icon={<Settings2 />}>
                <RecordFieldList>
                  {visible('recipient_role') ? (
                    <RecordField label={t('commissionConfigurations.form.recipient_role')}>
                      {option('recipient_role', configuration.recipient_role)}
                    </RecordField>
                  ) : null}
                  {visible('application_scope') ? (
                    <RecordField label={t('commissionConfigurations.form.application_scope')}>
                      {option('application_scope', configuration.application_scope)}
                    </RecordField>
                  ) : null}
                  {scopeRelationField && visible(scopeRelationField) ? (
                    <RecordField
                      label={
                        configuration.application_scope === 'PRODUCT'
                          ? t('commissionConfigurations.form.product_id')
                          : t('commissionConfigurations.form.product_category_id')
                      }
                    >
                      {configuration.product ? (
                        <RecordLink domain="products" id={configuration.product.id}>
                          {configuration.product.name}
                        </RecordLink>
                      ) : (
                        (configuration.product_category?.name ?? '–')
                      )}
                    </RecordField>
                  ) : null}
                  {visible('recipient_id') ? (
                    <RecordField label={t('commissionConfigurations.form.recipient_id')}>
                      {configuration.recipient && recipientDomain ? (
                        <RecordLink domain={recipientDomain} id={configuration.recipient.id}>
                          {configuration.recipient.name}
                        </RecordLink>
                      ) : (
                        (configuration.recipient?.name ?? '–')
                      )}
                    </RecordField>
                  ) : null}
                </RecordFieldList>
              </RecordSection>
            ) : null}

            {calculationSectionVisible ? (
              <RecordSection title={t('commissionConfigurations.detail.calculation')} icon={<CircleDollarSign />}>
                <RecordFieldList>
                  {visible('commission_type') ? (
                    <RecordField label={t('commissionConfigurations.form.commission_type')}>
                      {option('commission_type', configuration.commission_type)}
                    </RecordField>
                  ) : null}
                  {visible('value') ? (
                    <RecordField label={t('commissionConfigurations.form.value')}>{formattedValue}</RecordField>
                  ) : null}
                  {visible('priority') ? (
                    <RecordField label={t('commissionConfigurations.form.priority')}>
                      {configuration.priority}
                    </RecordField>
                  ) : null}
                  {visible('status') ? (
                    <RecordField label={t('commissionConfigurations.form.status')}>
                      <Badge variant={configuration.status === 'ACTIVE' ? 'default' : 'secondary'}>
                        {option('status', configuration.status)}
                      </Badge>
                    </RecordField>
                  ) : null}
                </RecordFieldList>
              </RecordSection>
            ) : null}

            {validitySectionVisible ? (
              <RecordSection title={t('commissionConfigurations.detail.validity')} icon={<CalendarRange />}>
                <RecordFieldList>
                  {visible('valid_from') ? (
                    <RecordField label={t('commissionConfigurations.form.valid_from')}>
                      {formatDate(configuration.valid_from) || EMPTY_DATE}
                    </RecordField>
                  ) : null}
                  {visible('valid_until') ? (
                    <RecordField label={t('commissionConfigurations.form.valid_until')}>
                      {formatDate(configuration.valid_until) || EMPTY_DATE}
                    </RecordField>
                  ) : null}
                  {visible('internal_note') ? (
                    <RecordField label={t('commissionConfigurations.form.internal_note')}>
                      {configuration.internal_note ?? '–'}
                    </RecordField>
                  ) : null}
                </RecordFieldList>
              </RecordSection>
            ) : null}
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {visible('updated_at') ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('commissionConfigurations.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {formatDateTime(configuration.updated_at)}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
