import { CalendarRange, CircleDollarSign, History, Settings2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import {
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CommissionConfigurationDetailWithPermissions } from './types'
import { formatDate } from '@/lib/formatting/date-display'

/** Placeholder for an open-ended validity bound. */
const EMPTY_DATE = '–'

export function CommissionConfigurationDetailView({
  configuration,
}: {
  configuration: CommissionConfigurationDetailWithPermissions
}) {
  const { t } = useTranslation()
  const visible = (field: string) =>
    configuration.permissions.fields[field]?.visible ?? true
  const scopeRelationField =
    configuration.application_scope === 'PRODUCT' ? 'product_id' : 'product_category_id'
  const scopeSectionVisible =
    visible('recipient_role') ||
    visible('application_scope') ||
    visible(scopeRelationField)
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
  const formattedValue =
    configuration.commission_type === 'PERCENTAGE'
      ? `${new Intl.NumberFormat(i18n.language, { maximumFractionDigits: 4 }).format(Number(configuration.value))}%`
      : new Intl.NumberFormat(i18n.language, { minimumFractionDigits: 2 }).format(Number(configuration.value))

  return (
    <DetailPanel>
      <DetailHero
        media={
          <DetailMonogram
            name={visible('name') ? configuration.name : t('commissionConfigurations.detail.title')}
            icon={<CircleDollarSign />}
          />
        }
        title={
          visible('name') ? configuration.name : t('commissionConfigurations.detail.title')
        }
      />
      {scopeSectionVisible ? (
      <DetailSection title={t('commissionConfigurations.detail.scope')} icon={<Settings2 />}>
        <DetailGrid>
          {visible('recipient_role') ? <DetailField label={t('commissionConfigurations.form.recipient_role')}>
            {option('recipient_role', configuration.recipient_role)}
          </DetailField> : null}
          {visible('application_scope') ? <DetailField label={t('commissionConfigurations.form.application_scope')}>
            {option('application_scope', configuration.application_scope)}
          </DetailField> : null}
          {visible(scopeRelationField) ? <DetailField
            label={
              configuration.application_scope === 'PRODUCT'
                ? t('commissionConfigurations.form.product_id')
                : t('commissionConfigurations.form.product_category_id')
            }
          >
            {configuration.product?.name ?? configuration.product_category?.name ?? '–'}
          </DetailField> : null}
        </DetailGrid>
      </DetailSection>
      ) : null}
      {calculationSectionVisible ? (
      <DetailSection title={t('commissionConfigurations.detail.calculation')} icon={<CircleDollarSign />}>
        <DetailGrid>
          {visible('commission_type') ? <DetailField label={t('commissionConfigurations.form.commission_type')}>
            {option('commission_type', configuration.commission_type)}
          </DetailField> : null}
          {visible('value') ? <DetailField label={t('commissionConfigurations.form.value')}>{formattedValue}</DetailField> : null}
          {visible('priority') ? <DetailField label={t('commissionConfigurations.form.priority')}>
            {configuration.priority}
          </DetailField> : null}
          {visible('status') ? <DetailField label={t('commissionConfigurations.form.status')}>
            <Badge variant={configuration.status === 'ACTIVE' ? 'default' : 'secondary'}>
              {option('status', configuration.status)}
            </Badge>
          </DetailField> : null}
        </DetailGrid>
      </DetailSection>
      ) : null}
      {validitySectionVisible ? (
      <DetailSection title={t('commissionConfigurations.detail.validity')} icon={<CalendarRange />}>
        <DetailGrid>
          {visible('valid_from') ? <DetailField label={t('commissionConfigurations.form.valid_from')}>
            {formatDate(configuration.valid_from) || EMPTY_DATE}
          </DetailField> : null}
          {visible('valid_until') ? <DetailField label={t('commissionConfigurations.form.valid_until')}>
            {formatDate(configuration.valid_until) || EMPTY_DATE}
          </DetailField> : null}
          {visible('internal_note') ? <DetailField label={t('commissionConfigurations.form.internal_note')}>
            {configuration.internal_note ?? '–'}
          </DetailField> : null}
        </DetailGrid>
      </DetailSection>
      ) : null}
      {configuration.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="commission-configurations" id={configuration.id} />
        </DetailSection>
      ) : null}
      {visible('updated_at') ? <DetailMeta label={t('commissionConfigurations.detail.updated_at')}>
        {formatDateTime(configuration.updated_at)}
      </DetailMeta> : null}
    </DetailPanel>
  )
}
