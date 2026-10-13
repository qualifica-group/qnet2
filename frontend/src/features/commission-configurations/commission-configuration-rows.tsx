import { CalendarRange, CircleDollarSign, Settings2 } from 'lucide-react'
import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { NumberInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { OptionInlineRow } from './commission-configuration-controls'
import { CommissionConfigurationRecipientRow } from './commission-configuration-recipient-row'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import { CommissionConfigurationScopeRow } from './commission-configuration-scope-row'
import { CommissionConfigurationValidityRow } from './commission-configuration-validity-row'
import {
  COMMISSION_STATUSES,
  COMMISSION_TYPES,
  type CommissionConfigurationRefs,
} from './types'

interface CommissionConfigurationSectionsProps {
  inline: InlineEdit
  canEdit: boolean
  refs: CommissionConfigurationRefs
}

const SCOPE_SECTION_FIELDS = [
  'name',
  'recipient_role',
  'recipient_id',
  'application_scope',
  'product_category_id',
  'product_id',
] as const
const CALCULATION_SECTION_FIELDS = ['commission_type', 'value', 'priority', 'status'] as const
const VALIDITY_SECTION_FIELDS = ['valid_from', 'valid_until', 'internal_note'] as const

/** `value` carries the unit of the commission type: a percentage or a fixed amount in euro. */
function CommissionValueRow({ inline, canEdit }: Pick<CommissionConfigurationSectionsProps, 'inline' | 'canEdit'>) {
  const { t } = useTranslation()
  const { control } = useFormContext<CommissionConfigurationFormValues>()
  const commissionType = useWatch({ control, name: 'commission_type' })

  return (
    <NumberInlineRow<CommissionConfigurationFormValues>
      name="value"
      label={t('commissionConfigurations.form.value')}
      inline={inline}
      canEdit={canEdit}
      min={0}
      step={0.0001}
      suffix={commissionType === 'PERCENTAGE' ? '%' : '€'}
    />
  )
}

/**
 * The configuration's three sections, shared by the detail (saved one row at
 * a time) and the create draft (kept until Salva). A section none of whose
 * fields the actor may see is not rendered.
 */
export function CommissionConfigurationSections({ inline, canEdit, refs }: CommissionConfigurationSectionsProps) {
  const { t } = useTranslation()
  const { field } = useResourcePermissions()
  const anyVisible = (names: readonly string[]) => names.some((name) => field(name).visible)

  return (
    <RecordSectionsGrid>
      {anyVisible(SCOPE_SECTION_FIELDS) ? (
        <RecordSection title={t('commissionConfigurations.detail.scope')} icon={<Settings2 />}>
          <RecordFieldList>
            <TextInlineRow<CommissionConfigurationFormValues>
              name="name"
              label={t('commissionConfigurations.form.name')}
              inline={inline}
              canEdit={canEdit}
            />
            <CommissionConfigurationRecipientRow inline={inline} canEdit={canEdit} recipient={refs.recipient} />
            <CommissionConfigurationScopeRow inline={inline} canEdit={canEdit} refs={refs} />
          </RecordFieldList>
        </RecordSection>
      ) : null}

      {anyVisible(CALCULATION_SECTION_FIELDS) ? (
        <RecordSection title={t('commissionConfigurations.detail.calculation')} icon={<CircleDollarSign />}>
          <RecordFieldList>
            <OptionInlineRow name="commission_type" options={COMMISSION_TYPES} inline={inline} canEdit={canEdit} />
            <CommissionValueRow inline={inline} canEdit={canEdit} />
            <NumberInlineRow<CommissionConfigurationFormValues>
              name="priority"
              label={t('commissionConfigurations.form.priority')}
              hint={t('commissionConfigurations.form.hints.priority')}
              inline={inline}
              canEdit={canEdit}
              step={1}
            />
            <OptionInlineRow
              name="status"
              options={COMMISSION_STATUSES}
              inline={inline}
              canEdit={canEdit}
              renderValue={(label, value) => (
                <Badge variant={value === 'ACTIVE' ? 'default' : 'secondary'}>{label}</Badge>
              )}
            />
          </RecordFieldList>
        </RecordSection>
      ) : null}

      {anyVisible(VALIDITY_SECTION_FIELDS) ? (
        <RecordSection title={t('commissionConfigurations.detail.validity')} icon={<CalendarRange />}>
          <RecordFieldList>
            <CommissionConfigurationValidityRow inline={inline} canEdit={canEdit} />
            <TextInlineRow<CommissionConfigurationFormValues>
              name="internal_note"
              label={t('commissionConfigurations.form.internal_note')}
              inline={inline}
              canEdit={canEdit}
              multiline
            />
          </RecordFieldList>
        </RecordSection>
      ) : null}
    </RecordSectionsGrid>
  )
}
