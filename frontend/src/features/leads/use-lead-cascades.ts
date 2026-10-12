import { useRef } from 'react'
import type { UseFormReturn } from 'react-hook-form'
import type { CampaignForSelectItem } from '@/features/campaigns/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserForSelectItem } from '@/features/users/for-select-api'
import { useLeadCampaignProductInterest } from '@/features/leads/use-lead-campaign-product-interest'
import type { LeadDetail } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/**
 * The lead's field cascades, shared by the create draft and the in-place
 * detail (spec 0217 D-3), each wired as a picker's `onItemChange` (an event
 * handler, never a derived-state effect):
 *
 * - Campagna -> Sede and Fonte prefill (project -> campaign -> lead chain,
 *   spec 0176 D-2), held back behind the products-of-interest confirmation
 *   when chosen products fall outside the new campaign (spec 0094 D-5).
 * - Operatore -> Sede, only while the Sede is still empty (AC-061, spec 0103
 *   D-13: a remote membership must not overwrite a chosen Sede).
 * - Sede -> Operatore: a real Sede change clears the Operatore, whose list it
 *   scopes (AC-062).
 *
 * `previousSiteIdRef` tells a real Sede pick apart from the programmatic
 * fills; `syncBaseline` re-anchors it (and the campaign's) on the value the
 * row opens with, since the form may have been saved or reset in between.
 */
export function useLeadCascades(form: UseFormReturn<LeadFormValues>, original: LeadDetail | null) {
  const previousSiteIdRef = useRef<number | null>(original?.operational_site_id ?? null)

  const applyCampaignPrefill = (item: ForSelectItem | null) => {
    const campaign = item as CampaignForSelectItem | null
    const site = campaign?.meta?.operational_site
    if (site != null) {
      form.setValue('operational_site_id', site.id, { shouldDirty: true, shouldValidate: true })
      previousSiteIdRef.current = site.id
    }
    const source = campaign?.meta?.source
    if (source != null) {
      form.setValue('source_id', source.id, { shouldDirty: true, shouldValidate: true })
    }
  }

  const productInterest = useLeadCampaignProductInterest({
    form,
    original,
    onCampaignApplied: applyCampaignPrefill,
  })

  const handleOperatorItemChange = (item: ForSelectItem | null) => {
    if (form.getValues('operational_site_id') != null) return
    const site = (item as UserForSelectItem | null)?.meta
    if (site?.operational_site_id == null) return
    form.setValue('operational_site_id', site.operational_site_id, { shouldDirty: true, shouldValidate: true })
    previousSiteIdRef.current = site.operational_site_id
  }

  const handleSiteItemChange = (item: ForSelectItem | null) => {
    const nextSiteId = item?.id ?? null
    if (nextSiteId !== previousSiteIdRef.current) {
      form.setValue('operator_id', null, { shouldDirty: true })
    }
    previousSiteIdRef.current = nextSiteId
  }

  const syncBaseline = () => {
    previousSiteIdRef.current = form.getValues('operational_site_id')
    productInterest.syncBaseline()
  }

  return {
    campaignCategoryIds: productInterest.campaignCategoryIds,
    isCampaignChangePending: productInterest.isCampaignChangePending,
    handleCampaignItemChange: productInterest.handleCampaignItemChange,
    handleOperatorItemChange,
    handleSiteItemChange,
    syncBaseline,
  }
}

/** The cascade handlers a record section wires into its pickers. */
export type LeadCascades = ReturnType<typeof useLeadCascades>
