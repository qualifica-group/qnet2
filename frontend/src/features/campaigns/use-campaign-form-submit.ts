import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { campaignDetailQueryKey, createCampaign, updateCampaign } from '@/features/campaigns/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/campaigns/campaign-form-payload'
import { editDefaults, type CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'

/**
 * Server-side field names mapped onto the form for 422 handling. `total_budget`
 * also carries BR-3's insufficient-budget message. `product_lines` is collected
 * apart (see below).
 */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'project_id',
  'description',
  'partner_id',
  'operational_site_id',
  'source_id',
  'pipeline_status_id',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'start_date',
  'end_date',
  'total_budget',
  'target_lead',
] as const

/** Every 422 key that names the `product_lines` collection or one of its rows (spec 0094). */
const PRODUCT_LINES_ERROR_PREFIXES = ['product_lines']

/**
 * Joins every 422 message whose key is `prefix` or `prefix.<anything>` (a
 * per-row key like `product_lines.0.product_category_id`) into one message:
 * the row editor has no per-row error slot.
 */
function collectPrefixedServerErrors(error: unknown, prefixes: string[]): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}

interface UseCampaignFormSubmitArgs {
  form: UseFormReturn<CampaignFormValues>
  mode: CampaignFormMode
  /** The custom fields' 422 paths (spec 0021), from `useCampaignForm`. */
  customFieldErrorPaths: string[]
  onSuccess: (campaign: CampaignDetail) => void
}

/**
 * Owns the campaign create/update submit: the payload (duplicate goes through
 * the create path), the partial PATCH, the cache seed and the server 422
 * mapping (BR-3's budget message lands on `total_budget`, verbatim). A
 * `product_lines` 422 surfaces as `serverError` (spec 0221 D-7). On the
 * in-place detail the form is reset on the saved record.
 */
export function useCampaignFormSubmit({ form, mode, customFieldErrorPaths, onSuccess }: UseCampaignFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const onSubmit = async (values: CampaignFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and seed the cached detail with
        // the full shape the server returns (permissions included).
        const saved = await updateCampaign(mode.campaign.id, buildUpdatePayload(values, mode.campaign))
        queryClient.setQueryData(campaignDetailQueryKey(mode.campaign.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved, saved.custom_fields ?? {}), { keepDirtyValues: false })
        toast.success(t('campaigns.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCampaign(buildCreatePayload(values))
      toast.success(t('campaigns.form.created'))
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFieldErrorPaths as Path<CampaignFormValues>[]),
      ])
      const productLinesMessage = collectPrefixedServerErrors(error, PRODUCT_LINES_ERROR_PREFIXES)
      if (productLinesMessage) {
        setServerError(productLinesMessage)
      } else if (!mappedScalar) {
        setServerError(t('campaigns.form.genericError'))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearServerError = () => setServerError(null)

  return { serverError, onSubmit, clearServerError }
}
