import { useEffect, useMemo, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { emptyProductLineRow } from '@/features/product-lines/types'
import { useDefaultCountryId } from '@/features/geo/use-default-country'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import {
  buildCreateProjectSchema,
  buildUpdateProjectSchema,
  type CreateProjectFormValues,
} from '@/features/projects/project-schema'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'

export type ProjectFormValues = CreateProjectFormValues

/**
 * Maps every form field shared by edit and duplicate (row action "duplicate")
 * from a loaded project — `code`/`name` are excluded since the two modes
 * diverge there (edit keeps the saved code/name, duplicate takes a new code
 * and the copy suffix on the name).
 */
function mapProjectToFormValues(
  project: ProjectDetail,
  customFieldsDefaultValues: Record<string, CustomFieldValue>,
): Omit<ProjectFormValues, 'code' | 'name'> {
  return {
    description: project.description,
    pipeline_status_id: project.pipeline_status_id,
    country_id: project.country_id,
    state_id: project.state_id,
    province_id: project.province_id,
    city_id: project.city_id,
    // Spec 0132: `root_category_id` starts `null` — the shared `ProductLinesField`
    // resolves it at render time from the cached category tree.
    product_lines: project.product_lines.map((line) => ({
      root_category_id: null,
      product_category_id: line.product_category.id,
    })),
    partner_id: project.partner_id,
    operational_site_id: project.operational_site_id,
    start_date: project.start_date ?? '',
    end_date: project.end_date ?? '',
    total_budget: project.total_budget === null ? null : Number(project.total_budget),
    target_lead: project.target_lead,
    custom_fields: customFieldsDefaultValues,
  }
}

/** The persisted project as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(project: ProjectDetail, customFields: Record<string, CustomFieldValue>): ProjectFormValues {
  return { code: project.code, name: project.name, ...mapProjectToFormValues(project, customFields) }
}

interface UseProjectFormArgs {
  mode: ProjectFormMode
  /** Create/duplicate: sequential code suggestion prefilled into the `code` default (spec 0025). */
  initialCode?: string
}

/**
 * Owns the RHF/Zod wiring of the project create/duplicate form and of the
 * in-place detail (`useProjectInlineEdit`, edit mode), custom fields included
 * (spec 0021). Submission lives in `useProjectFormSubmit`.
 *
 * Edit mode IS the project detail (spec 0220): the persisted record can change
 * under the form, so `values` re-syncs it while `keepDirtyValues` preserves
 * the row still being edited.
 */
export function useProjectForm({ mode, initialCode }: UseProjectFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'
  // Only a bare create preselects the system default status below (duplicate
  // keeps the copied source status; edit never applies it either).
  const isStandaloneCreate = mode.type === 'create'

  // Duplicate seeds its custom values from the source, exactly like edit,
  // even though it submits through the create payload builder.
  const customFields = useCustomFieldsForm(
    'projects',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.project.custom_fields }
      : mode.type === 'duplicate'
        ? { type: 'edit', customFields: mode.source.custom_fields }
        : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProjectSchema(t, customFields.schema)
        : buildCreateProjectSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ProjectFormValues>(() => {
    if (mode.type === 'edit') {
      return editDefaults(mode.project, customFields.defaultValues)
    }
    if (mode.type === 'duplicate') {
      return {
        code: initialCode ?? '',
        name: mode.source.name + t('common.copySuffix'),
        ...mapProjectToFormValues(mode.source, customFields.defaultValues),
      }
    }
    return {
      code: initialCode ?? '',
      name: '',
      description: null,
      pipeline_status_id: null,
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
      // At least one row is mandatory, so the create form opens on ONE empty
      // row instead of zero (user directive 2026-07-29).
      product_lines: [emptyProductLineRow()],
      partner_id: null,
      operational_site_id: null,
      start_date: '',
      end_date: '',
      total_budget: null,
      target_lead: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues, initialCode, t])

  const form = useForm<ProjectFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  // Spec 0039 D-3: preselect the system "Nuovo" status on a bare create, once
  // the for-select resolves, but only if the field is still untouched — the
  // user always wins. An effect is the correct tool: the value only becomes
  // known asynchronously, after the form's defaults are fixed.
  const defaultStatus = useDefaultSystemStatusId(PROJECT_STATUSES_FOR_SELECT_RESOURCE, 'new', isStandaloneCreate)
  const appliedDefaultStatus = useRef(false)
  useEffect(() => {
    if (!isStandaloneCreate || appliedDefaultStatus.current || defaultStatus.data == null) {
      return
    }
    if (form.getFieldState('pipeline_status_id').isDirty) {
      appliedDefaultStatus.current = true
      return
    }
    form.setValue('pipeline_status_id', defaultStatus.data)
    appliedDefaultStatus.current = true
  }, [isStandaloneCreate, defaultStatus.data, form])

  // Same rule for the national default country (spec 0027 BR-4, `GeoSelect`):
  // the geography row starts closed (spec 0220), so its editor is not mounted
  // to preselect it — the draft takes it here, still only if untouched.
  const defaultCountryId = useDefaultCountryId()
  const appliedDefaultCountry = useRef(false)
  useEffect(() => {
    if (!isStandaloneCreate || appliedDefaultCountry.current || defaultCountryId === null) {
      return
    }
    appliedDefaultCountry.current = true
    if (!form.getFieldState('country_id').isDirty && form.getValues('country_id') === null) {
      form.setValue('country_id', defaultCountryId)
    }
  }, [isStandaloneCreate, defaultCountryId, form])

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
