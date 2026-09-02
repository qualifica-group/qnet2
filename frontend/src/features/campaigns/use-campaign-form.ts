import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { campaignDetailQueryKey, createCampaign, updateCampaign } from '@/features/campaigns/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/campaigns/campaign-form-payload'
import {
  buildCreateCampaignSchema,
  buildUpdateCampaignSchema,
  type CreateCampaignFormValues,
} from '@/features/campaigns/campaign-schema'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import { emptyProductLineRow } from '@/features/product-lines/types'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** Server-side field names mapped onto the form for 422 handling. `total_budget` also carries BR-3's insufficient-budget message. `product_lines` is handled separately (banner, see `collectPrefixedServerErrors`). */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'project_id',
  'description',
  'partner_id',
  'operational_site_id',
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
 * per-row key like `product_lines.0.business_function_id`), for a single
 * banner above the field. Reimplemented locally (not imported) from
 * `request-management/use-request-create-form.ts`'s `collectPrefixedServerErrors`:
 * that file is owned by another feature/session, so this ~12-line helper is
 * duplicated rather than creating a cross-feature dependency on a file
 * currently changing under a different owner.
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

export type CampaignFormValues = CreateCampaignFormValues

/**
 * Maps every form field shared by edit and duplicate (row action "duplicate")
 * from a loaded campaign — `code`/`name` are excluded since the two modes
 * diverge there (edit keeps the saved code/name, duplicate clears the code
 * for server regeneration and appends the copy suffix to the name); `project_id`
 * IS copied (spec: duplicate carries the linked project over). Kept as a
 * single source of truth so the two `defaultValues` branches below never
 * drift apart.
 */
function mapCampaignToFormValues(
  campaign: CampaignDetail,
  customFieldsDefaultValues: Record<string, CustomFieldValue>,
): Omit<CampaignFormValues, 'code' | 'name'> {
  return {
    project_id: campaign.project_id,
    description: campaign.description,
    partner_id: campaign.partner_id,
    operational_site_id: campaign.operational_site_id,
    pipeline_status_id: campaign.pipeline_status_id,
    product_lines: campaign.product_lines.map((line) => ({
      business_function_id: line.business_function.id,
      product_category_id: line.product_category.id,
    })),
    country_id: campaign.country_id,
    state_id: campaign.state_id,
    province_id: campaign.province_id,
    city_id: campaign.city_id,
    geo_locked_levels: campaign.geo_locked_levels,
    start_date: campaign.start_date ?? '',
    end_date: campaign.end_date ?? '',
    total_budget: campaign.total_budget === null ? null : Number(campaign.total_budget),
    target_lead: campaign.target_lead,
    custom_fields: customFieldsDefaultValues,
  }
}

interface UseCampaignFormArgs {
  mode: CampaignFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (campaign: CampaignDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (spec 0025). */
  initialCode?: string
}

/**
 * Owns every non-render concern of `CampaignForm`: RHF/Zod wiring, default
 * values, server 422 mapping (BR-3's budget message included, verbatim) and
 * the create/update submit. The component stays UI-only; this hook is the
 * orchestration point (`onSubmit`).
 */
export function useCampaignForm({ mode, onSuccess, initialCode }: UseCampaignFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const [productLinesError, setProductLinesError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'
  // Only a bare standalone create preselects the system default status below
  // (duplicate must keep the copied source status; edit never applies it either).
  const isStandaloneCreate = mode.type === 'create'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  // Duplicate seeds its values from the source, exactly like edit, even
  // though it submits through the create payload builder.
  const customFields = useCustomFieldsForm(
    'campaigns',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.campaign.custom_fields }
      : mode.type === 'duplicate'
        ? { type: 'edit', customFields: mode.source.custom_fields }
        : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateCampaignSchema(t, customFields.schema)
        : buildCreateCampaignSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<CampaignFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        code: mode.campaign.code,
        name: mode.campaign.name,
        ...mapCampaignToFormValues(mode.campaign, customFields.defaultValues),
      }
    }
    if (mode.type === 'duplicate') {
      return {
        code: initialCode ?? '',
        name: mode.source.name + t('common.copySuffix'),
        ...mapCampaignToFormValues(mode.source, customFields.defaultValues),
      }
    }
    return {
      code: initialCode ?? '',
      project_id: null,
      name: '',
      description: null,
      partner_id: null,
      operational_site_id: null,
      pipeline_status_id: null,
      // User directive 2026-07-29 (mirrors project/opportunity/request-management
      // create forms): a bare create is always standalone, so it opens on ONE
      // empty row instead of zero — pure UX friction otherwise.
      product_lines: [emptyProductLineRow()],
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
      geo_locked_levels: [],
      start_date: '',
      end_date: '',
      total_budget: null,
      target_lead: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues, initialCode, t])

  const form = useForm<CampaignFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // Spec 0039 D-3: preselect the system "Nuovo" status on a standalone bare
  // create, once the for-select resolves, but only if the field is still
  // untouched — the user (or a faster manual pick, or linking a project,
  // which overwrites it itself) always wins. Gated on `isStandaloneCreate`
  // (not `!isEdit`): duplicate is not edit either, but it already prefilled
  // `pipeline_status_id`/`project_id` from the source and must keep them, not
  // have the status silently overwritten by the system default. Guarded to
  // apply at most once: an effect is the correct tool here, since RHF's
  // `defaultValues` are fixed at mount and this value only becomes known
  // asynchronously.
  const defaultStatus = useDefaultSystemStatusId(
    PROJECT_STATUSES_FOR_SELECT_RESOURCE,
    'new',
    isStandaloneCreate,
  )
  const appliedDefaultStatus = useRef(false)
  useEffect(() => {
    if (!isStandaloneCreate || appliedDefaultStatus.current || defaultStatus.data == null) {
      return
    }
    if (form.getValues('project_id') !== null || form.getFieldState('pipeline_status_id').isDirty) {
      appliedDefaultStatus.current = true
      return
    }
    form.setValue('pipeline_status_id', defaultStatus.data)
    appliedDefaultStatus.current = true
  }, [isStandaloneCreate, defaultStatus.data, form])

  const onSubmit = async (values: CampaignFormValues) => {
    setServerError(null)
    setProductLinesError(null)
    const errorFields: Path<CampaignFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<CampaignFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateCampaign(mode.campaign.id, buildUpdatePayload(values, mode.campaign))
        queryClient.setQueryData(campaignDetailQueryKey(mode.campaign.id), saved)
        toast.success(t('campaigns.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCampaign(buildCreatePayload(values))
      toast.success(t('campaigns.form.created'))
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, errorFields)
      const productLinesMessage = collectPrefixedServerErrors(error, PRODUCT_LINES_ERROR_PREFIXES)
      setProductLinesError(productLinesMessage)
      if (!mappedScalar && !productLinesMessage) {
        setServerError(t('campaigns.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    productLinesError,
    onSubmit,
  }
}
