import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useOpportunityManagerLabels } from '@/features/opportunities/use-opportunity-manager-labels'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'

interface OpportunityTeamSectionProps {
  control: Control<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  /** Create-only requirement; existing opportunities may keep a null supervisor. */
  supervisorRequired: boolean
  className?: string
}

/** Converts the wire `manager_labels` (string position keys) to `ManagerSlotsField`'s own `Record<number, string>`. */
function toSlotLabels(source: Record<string, string>): Record<number, string> | undefined {
  const entries = Object.entries(source).map(([position, label]) => [Number(position), label] as const)
  return entries.length > 0 ? Object.fromEntries(entries) : undefined
}

/**
 * The opportunity's team relations: supervisor and the shared, ordered "G.A. n"
 * manager slots (`ManagerSlotsField`, extracted from Registries — spec 0040).
 *
 * "G.A. n" relabeling (spec 0080): resolved LIVE from the form's own current
 * `product_lines` (`useOpportunityManagerLabels`), identically in create and
 * edit — never from the persisted `OpportunityDetail.manager_labels`, so a
 * product-line change re-labels the slots immediately, before any save.
 */
export function OpportunityTeamSection({
  control,
  selectedItems,
  supervisorRequired,
  className,
}: OpportunityTeamSectionProps) {
  const { t } = useTranslation()
  const productLines = useWatch({ control, name: 'product_lines' })
  const managerLabels = useOpportunityManagerLabels(productLines)
  const slotLabels = toSlotLabels(managerLabels)

  return (
    <FormSection
      icon={Users}
      title={t('opportunities.form.sections.team.title')}
      description={t('opportunities.form.sections.team.description')}
      className={className}
    >
      <RelationSelectField
        control={control}
        name="supervisor_id"
        metaKey="supervisor_id"
        label={t('opportunities.form.supervisor')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('opportunities.form.supervisorSearch')}
        selected={selectedItems.supervisor}
        required={supervisorRequired}
        showAvatar
        placeholder={t('opportunities.form.selectPlaceholder')}
        emptyLabel={t('opportunities.form.selectEmpty')}
        errorLabel={t('opportunities.form.selectError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <MetaField
        control={control}
        name="manager_slots"
        metaKey="manager_slots"
        label={t('opportunities.form.managers')}
      >
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={selectedItems.managers}
            disabled={disabled}
            labels={slotLabels}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
