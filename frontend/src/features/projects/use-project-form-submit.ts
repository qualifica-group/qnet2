import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { createProject, projectDetailQueryKey, updateProject } from '@/features/projects/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/projects/project-form-payload'
import { editDefaults, type ProjectFormValues } from '@/features/projects/use-project-form'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'

/** Domain key of the module statistics (mirrors `PROJECTS_DOMAIN` in `projects-table.tsx`). */
const PROJECTS_DOMAIN = 'projects'

/** Server-side field names mapped onto the form for 422 handling. `product_lines` is collected apart (see below). */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'pipeline_status_id',
  'description',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'partner_id',
  'operational_site_id',
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

interface UseProjectFormSubmitArgs {
  form: UseFormReturn<ProjectFormValues>
  mode: ProjectFormMode
  /** The custom fields' 422 paths (spec 0021), from `useProjectForm`. */
  customFieldErrorPaths: string[]
  /** Create/duplicate: the `next-code` suggestion prefilled into `code`, omitted on submit when untouched. */
  suggestedCode?: string
  onSuccess: (project: ProjectDetail) => void
}

/**
 * Owns the project create/update submit: the payload (duplicate goes through
 * the create path), the partial PATCH, the cache seed and the server 422
 * mapping. A `product_lines` 422 surfaces as `serverError`, the row editor
 * having no slot of its own. On the in-place detail (spec 0220) the form is
 * reset on the saved record.
 */
export function useProjectFormSubmit({
  form,
  mode,
  customFieldErrorPaths,
  suggestedCode,
  onSuccess,
}: UseProjectFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PROJECTS_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)

  const onSubmit = async (values: ProjectFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and seed the cached detail with
        // the full shape the server returns (permissions included).
        const saved = await updateProject(mode.project.id, buildUpdatePayload(values, mode.project))
        queryClient.setQueryData(projectDetailQueryKey(mode.project.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved, saved.custom_fields ?? {}), { keepDirtyValues: false })
        toast.success(t('projects.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      const created = await createProject(buildCreatePayload(values, suggestedCode))
      toast.success(t('projects.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFieldErrorPaths as Path<ProjectFormValues>[]),
      ])
      const productLinesMessage = collectPrefixedServerErrors(error, PRODUCT_LINES_ERROR_PREFIXES)
      if (productLinesMessage) {
        setServerError(productLinesMessage)
      } else if (!mappedScalar) {
        setServerError(t('projects.form.genericError'))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearServerError = () => setServerError(null)

  return { serverError, onSubmit, clearServerError }
}
