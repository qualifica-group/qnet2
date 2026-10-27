import { useTranslation } from 'react-i18next'
import { TrendingUp } from 'lucide-react'
import type { Control, FieldValues, Path } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import {
  OpportunityDateField,
  OpportunityEstimatedValueField,
  OpportunitySuccessProbabilityField,
} from '@/features/opportunities/opportunity-fields'
import type { RequestForecastValues } from '@/features/request-management/request-forecast'

interface RequestForecastSectionProps<TFieldValues extends RequestForecastValues & FieldValues> {
  control: Control<TFieldValues>
}

/**
 * "Previsione commerciale" (spec 0313, D-5): the Opportunity's forecast trio,
 * edited from the work panel and from the create form with the very controls
 * of the Opportunities form. The values are the Opportunity's own, so sibling
 * requests of the same deal share them.
 *
 * Gated by `MetaField` like every other field: the panel's permission envelope
 * may lock or hide each one; the create form has no envelope, so all three are
 * editable there.
 */
export function RequestForecastSection<TFieldValues extends RequestForecastValues & FieldValues>({
  control,
}: RequestForecastSectionProps<TFieldValues>) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={TrendingUp}
      title={t('requestManagement.workPanel.forecast.title')}
      description={t('requestManagement.workPanel.forecast.description')}
      className="min-w-0"
    >
      <div className="flex flex-col gap-3">
        <OpportunityDateField
          control={control}
          name={'expected_close_date' as Path<TFieldValues>}
          label={t('opportunities.form.expectedCloseDate')}
        />
        <OpportunityEstimatedValueField control={control} name={'estimated_value' as Path<TFieldValues>} />
        <OpportunitySuccessProbabilityField control={control} name={'success_probability' as Path<TFieldValues>} />
      </div>
    </FormSection>
  )
}
