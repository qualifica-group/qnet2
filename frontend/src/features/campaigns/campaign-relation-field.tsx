import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import type { CampaignRelationRef } from '@/features/campaigns/types'

/**
 * The campaign's single-relation fields, all sharing the exact same picker
 * shape: Partner, Sede and Fonte (always the campaign's own) and the
 * `pipeline_status_id` BR-2 classification field, whose row has no pencil
 * while the campaign is linked (spec 0221 D-5). The geo levels follow BR-5 in
 * `<GeoSelect>`, the product lines `ProductLinesField`.
 */
type CampaignRelationFieldName = 'partner_id' | 'operational_site_id' | 'source_id' | 'pipeline_status_id'

interface CampaignRelationFieldProps {
  control: Control<CampaignFormValues>
  name: CampaignRelationFieldName
  metaKey: string
  label: string
  resource: string
  searchPlaceholder: string
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: CampaignRelationRef | null
  /** Optional explanatory tooltip rendered next to the label, forwarded to `RelationSelectField`'s `hint`. */
  hint?: string
}

/** Campaign-specific i18n binding of the shared `RelationSelectField` (spec 0024 M7). */
export function CampaignRelationField({
  control,
  name,
  metaKey,
  label,
  resource,
  searchPlaceholder,
  selected,
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
      placeholder={t('campaigns.form.selectPlaceholder')}
      emptyLabel={t('campaigns.form.selectEmpty')}
      errorLabel={t('campaigns.form.selectError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}
