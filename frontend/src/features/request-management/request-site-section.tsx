import { useTranslation } from 'react-i18next'
import { MapPin } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestRelationRef } from '@/features/request-management/types'

interface RequestSiteSectionProps {
  control: Control<RequestWorkFormValues>
  /** Spec 0056: the operational site's `{id,label}` ref, converted to `{id,name}` by the caller (`toRelationFieldRef`). */
  operationalSite: RequestRelationRef | null
  /**
   * The operator half of the Sede <-> Operatore link the FORM owns
   * (`useRequestSiteOperatorLink`, spec 0097 rev-2 D-7): this card only
   * reports the Sede it takes and shows the one a picked operator hydrated.
   */
  autoFilledSite: ForSelectItem | null
  onSiteItemChange: (item: ForSelectItem | null) => void
}

/**
 * "Sede operativa" as its own card, immediately above the Team it scopes
 * (user directive 2026-09-10) — the work panel's copy of the create form's
 * `request-create-site-section.tsx`, which the same directive introduced.
 * It used to be the third field of "Attribuzione": the Sede IS attribution,
 * but what the operator does with it is pick the site whose people the team
 * slots then list.
 *
 * Metadata-driven like every other control of this panel, so its gating comes
 * from the server-derived `permissions` block. Strings stay under the
 * `workPanel.attribution.*` namespace they were written in: same field, moved,
 * and duplicated keys would be two copies of one label free to drift apart.
 */
export function RequestSiteSection({
  control,
  operationalSite,
  autoFilledSite,
  onSiteItemChange,
}: RequestSiteSectionProps) {
  const { t } = useTranslation()
  const label = t('requestManagement.workPanel.attribution.operationalSite', {
    defaultValue: 'Operational site',
  })

  return (
    <FormSection icon={MapPin} title={label} className="min-w-0">
      <RelationSelectField
        control={control}
        name="operational_site_id"
        metaKey="operational_site_id"
        label={label}
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('requestManagement.workPanel.attribution.operationalSiteSearch', {
          defaultValue: 'Search a site',
        })}
        selected={autoFilledSite ? { id: autoFilledSite.id, name: autoFilledSite.label } : operationalSite}
        onItemChange={onSiteItemChange}
        placeholder={t('requestManagement.workPanel.attribution.selectPlaceholder', {
          defaultValue: 'Select',
        })}
        emptyLabel={t('requestManagement.workPanel.attribution.selectEmpty', { defaultValue: 'No results' })}
        errorLabel={t('requestManagement.workPanel.attribution.selectError', {
          defaultValue: 'Could not load the options.',
        })}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />
    </FormSection>
  )
}
