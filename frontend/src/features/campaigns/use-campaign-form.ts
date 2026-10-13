import { useEffect, useMemo, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import {
  buildCreateCampaignSchema,
  buildUpdateCampaignSchema,
  type CreateCampaignFormValues,
} from '@/features/campaigns/campaign-schema'
import { useDefaultCountryId } from '@/features/geo/use-default-country'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import { emptyProductLineRow } from '@/features/product-lines/types'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import type { CustomFieldValue } from '@/features/custom-fields/types'

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
    source_id: campaign.source_id,
    pipeline_status_id: campaign.pipeline_status_id,
    // Spec 0132: `root_category_id` starts `null` — the shared `ProductLinesField`
    // resolves it at render time from the cached category tree.
    product_lines: campaign.product_lines.map((line) => ({
      root_category_id: null,
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

/** The persisted campaign as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(
  campaign: CampaignDetail,
  customFields: Record<string, CustomFieldValue>,
): CampaignFormValues {
  return { code: campaign.code, name: campaign.name, ...mapCampaignToFormValues(campaign, customFields) }
}

interface UseCampaignFormArgs {
  mode: CampaignFormMode
  /** Create/duplicate: sequential code suggestion prefilled into the `code` default (spec 0025). */
  initialCode?: string
}

/**
 * Owns the RHF/Zod wiring of the campaign create/duplicate form and of the
 * in-place detail (`useCampaignInlineEdit`, edit mode), custom fields included
 * (spec 0021). Submission lives in `useCampaignFormSubmit`.
 *
 * Edit mode IS the campaign detail (spec 0221): the persisted record can
 * change under the form, so `values` re-syncs it while `keepDirtyValues`
 * preserves the row still being edited.
 */
export function useCampaignForm({ mode, initialCode }: UseCampaignFormArgs) {
  const { t } = useTranslation()

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
      return editDefaults(mode.campaign, customFields.defaultValues)
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
      source_id: null,
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
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
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

  // Same rule for the national default country (spec 0027, `GeoSelect`): the
  // geography row starts closed (spec 0221 D-6), so its editor is not mounted
  // to preselect it — the draft takes it here, only while untouched and
  // standalone (a linked project brings its own levels).
  const defaultCountryId = useDefaultCountryId()
  const appliedDefaultCountry = useRef(false)
  useEffect(() => {
    if (!isStandaloneCreate || appliedDefaultCountry.current || defaultCountryId === null) {
      return
    }
    appliedDefaultCountry.current = true
    const untouched = !form.getFieldState('country_id').isDirty && form.getValues('country_id') === null
    if (untouched && form.getValues('project_id') === null) {
      form.setValue('country_id', defaultCountryId)
    }
  }, [isStandaloneCreate, defaultCountryId, form])

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
