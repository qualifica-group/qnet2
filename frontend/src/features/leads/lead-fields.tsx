import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { GENERAL_NOTES_TEXTAREA_CLASS } from '@/components/record-form/layout'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { CAMPAIGNS_FOR_SELECT_RESOURCE, type CampaignForSelectItem } from '@/features/campaigns/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { NOTES_MAX_LENGTH } from '@/features/leads/lead-schema'
import type { LeadProductOfInterest } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'
import { cn } from '@/lib/utils'

/*
 * The lead's fields, one component each, shared by the detail's in-place
 * rows and the create draft's rows (spec 0217).
 */

interface FieldProps {
  control: Control<LeadFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

interface CascadingFieldProps extends SelectedFieldProps {
  /** The cascade a real pick triggers (`useLeadCascades`). */
  onItemChange: (item: ForSelectItem | null) => void
}

/** Stable empty set: a fresh `[]` per render would break the label hook's memo. */
const NO_IDS: number[] = []

function useSelectLabels() {
  const { t } = useTranslation()
  return {
    placeholder: t('leads.form.selectPlaceholder'),
    emptyLabel: t('leads.form.selectEmpty'),
    errorLabel: t('leads.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}

/** "Anagrafica" (BR-1, spec 0041 D-1: the lead's identity). */
export function LeadRegistryField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="registry_id"
      metaKey="registry_id"
      label={t('leads.form.registry')}
      resource={REGISTRIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('leads.form.registrySearch')}
      selected={selected}
      required
      {...useSelectLabels()}
    />
  )
}

/** "Campagna" (BR-1): a pick prefills Sede and Fonte and guards the products (`useLeadCascades`). */
export function LeadCampaignField({ control, selected, onItemChange }: CascadingFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="campaign_id"
      metaKey="campaign_id"
      label={t('leads.form.campaign')}
      resource={CAMPAIGNS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('leads.form.campaignSearch')}
      selected={selected}
      required
      onItemChange={onItemChange}
      {...useSelectLabels()}
    />
  )
}

/** "Sede": a real change clears the Operatore it scopes (AC-062). */
export function LeadSiteField({ control, selected, onItemChange }: CascadingFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="operational_site_id"
      metaKey="operational_site_id"
      label={t('leads.form.operationalSite')}
      hint={t('leads.form.hints.operationalSite')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('leads.form.operationalSiteSearch')}
      selected={selected}
      onItemChange={onItemChange}
      {...useSelectLabels()}
    />
  )
}

/** "Operatore": listed for the chosen Sede (AC-060), fills an empty Sede (AC-061). */
export function LeadOperatorField({ control, selected, onItemChange }: CascadingFieldProps) {
  const { t } = useTranslation()
  const siteId = useWatch({ control, name: 'operational_site_id' })
  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <RelationSelectField
        control={control}
        name="operator_id"
        metaKey="operator_id"
        label={t('leads.form.operator')}
        hint={t('leads.form.hints.operator')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('leads.form.operatorSearch')}
        selected={selected}
        onItemChange={onItemChange}
        params={siteId != null ? { operational_site_id: siteId } : undefined}
        showAvatar
        {...useSelectLabels()}
      />
      {siteId != null ? (
        <p className="text-xs text-muted-foreground">{t('leads.form.hints.operatorFilteredBySite')}</p>
      ) : null}
    </div>
  )
}

/** "Fonte" (spec 0176 D-2): required, prefilled from the Campaign when it has one. */
export function LeadSourceField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="source_id"
      metaKey="source_id"
      label={t('leads.form.source')}
      hint={t('leads.form.hints.source')}
      resource={SOURCES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('leads.form.sourceSearch')}
      selected={selected}
      required
      {...useSelectLabels()}
    />
  )
}

/** "Note": written inside their callout (`NotesCalloutRow`), with the length counter. */
export function LeadNotesField({ control }: FieldProps) {
  const { t } = useTranslation()
  const notesLength = useWatch({ control, name: 'notes' })?.length ?? 0

  return (
    <MetaField control={control} name="notes" metaKey="notes" label={t('leads.form.notes')}>
      {({ field, disabled, readOnly }) => (
        <div className="flex flex-col gap-1">
          <FormControl>
            <Textarea
              className={GENERAL_NOTES_TEXTAREA_CLASS}
              rows={4}
              placeholder={t('leads.form.notesPlaceholder')}
              disabled={disabled}
              readOnly={readOnly}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
          <span
            aria-hidden="true"
            className={cn(
              'self-end text-xs tabular-nums',
              notesLength > NOTES_MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
            )}
          >
            {notesLength}/{NOTES_MAX_LENGTH}
          </span>
        </div>
      )}
    </MetaField>
  )
}

interface LeadProductsFieldProps extends FieldProps {
  /** Already-known products, so the chips never fall back to `#id`. */
  knownProducts: LeadProductOfInterest[]
  /** The categories the cascade last applied (a just-picked campaign), until the campaign's own resolve. */
  fallbackCategoryIds: number[]
}

/**
 * "Prodotti di interesse" (spec 0094): scoped to the chosen Campaign's
 * effective categories, read from the campaigns for-select `meta` (the same
 * payload its picker resolves), and disabled until a Campaign is chosen.
 */
export function LeadProductsField({ control, knownProducts, fallbackCategoryIds }: LeadProductsFieldProps) {
  const { t } = useTranslation()
  const campaignId = useWatch({ control, name: 'campaign_id' })
  const campaigns = useForSelectLabels({
    resource: CAMPAIGNS_FOR_SELECT_RESOURCE,
    ids: campaignId != null ? [campaignId] : NO_IDS,
  })
  const resolved = campaignId != null ? (campaigns.get(campaignId) as CampaignForSelectItem | undefined) : undefined
  const categoryIds = resolved?.meta?.product_category_ids ?? fallbackCategoryIds

  const selectedItems = useMemo<ForSelectItem[]>(
    () =>
      knownProducts.map((product) => ({
        id: product.id,
        label: product.name,
        subtitle: product.product_category?.name ?? null,
      })),
    [knownProducts],
  )

  return (
    <MetaField
      control={control}
      name="products_of_interest"
      metaKey="products_of_interest"
      label={t('products.ofInterest.fieldLabel')}
    >
      {({ field, disabled }) => (
        <FormControl>
          <ProductsOfInterestField
            value={field.value}
            onChange={field.onChange}
            categoryIds={categoryIds}
            selectedItems={selectedItems}
            disabled={disabled || campaignId == null}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Converti automaticamente in Opportunita'" (spec 0044): create only. */
export function LeadConvertField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="convert_to_opportunity"
      metaKey="convert_to_opportunity"
      label={t('leads.form.convertToOpportunity')}
      description={<FormDescription>{t('leads.form.convertToOpportunityHint')}</FormDescription>}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}
