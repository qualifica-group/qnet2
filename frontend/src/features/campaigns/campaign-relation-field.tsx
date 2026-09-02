import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import type { CampaignRelationRef } from '@/features/campaigns/types'

/**
 * The campaign's 3 single-relation fields, all sharing the exact same picker
 * shape: 2 always-own/editable (partner, operational_site) and the
 * `pipeline_status_id` BR-2 classification field, only editable while the
 * campaign is standalone (`forceDisabled` while linked, AC-042/AC-043).
 * `state_id` left this group (spec 0027 D-3): it is now one of the 4 geo
 * fields rendered by `<GeoSelect>`, following BR-5 instead. `business_function_id`/
 * `product_category_id` left this group too (spec 0094): they are now the
 * `product_lines` row collection, rendered by `ProductLinesField` directly in
 * `CampaignFormBody` (not through this thin wrapper).
 */
type CampaignRelationFieldName = 'partner_id' | 'operational_site_id' | 'pipeline_status_id'

interface CampaignRelationFieldProps {
  control: Control<CampaignFormValues>
  name: CampaignRelationFieldName
  metaKey: string
  label: string
  resource: string
  searchPlaceholder: string
  /** The loaded detail's hydrated `{id, name}` projection for this relation (edit mode), or the just-picked project's ref (create mode, AC-042). */
  selected: CampaignRelationRef | null
  /** Forces the field read-only regardless of field permissions: true for the 3 classification fields while `project_id` is set (BR-2). */
  forceDisabled?: boolean
  /** Required-marker override for the 3 classification fields (required only while standalone, BR-2); forwarded to `RelationSelectField`. */
  required?: boolean
  /** Scopes the picker to a parent value (product category -> business function, spec 0023 REV); forwarded to `RelationSelectField.params`. */
  params?: Record<string, string | number>
  /** Fired after the value changes, with the new value; forwarded to `RelationSelectField.onValueChange`. */
  onValueChange?: (next: number | null) => void
  /** Optional explanatory tooltip rendered next to the label, forwarded to `RelationSelectField`'s `hint`. */
  hint?: string
}

/**
 * Campaign-specific i18n binding of the shared `RelationSelectField` (spec
 * 0024 M7). Kept as a thin wrapper (rather than inlining the shared component
 * at every call site in `CampaignFormBody`) so the campaign relation fields'
 * call sites stay unchanged.
 */
export function CampaignRelationField({
  control,
  name,
  metaKey,
  label,
  resource,
  searchPlaceholder,
  selected,
  forceDisabled = false,
  required,
  params,
  onValueChange,
  hint,
}: CampaignRelationFieldProps) {
  const { t } = useTranslation()

  return (
    <RelationSelectField
      control={control}
      name={name}
      metaKey={metaKey}
      label={label}
      hint={hint}
      resource={resource}
      searchPlaceholder={searchPlaceholder}
      selected={selected}
      forceDisabled={forceDisabled}
      required={required}
      params={params}
      onValueChange={onValueChange}
      placeholder={t('campaigns.form.selectPlaceholder')}
      emptyLabel={t('campaigns.form.selectEmpty')}
      errorLabel={t('campaigns.form.selectError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}
