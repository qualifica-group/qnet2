import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createSector, updateSector } from '@/features/sectors/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/sectors/sector-form-payload'
import {
  buildCreateSectorSchema,
  buildUpdateSectorSchema,
  type CreateSectorFormValues,
} from '@/features/sectors/sector-schema'
import { sectorKeys } from '@/features/sectors/query-keys'
import type { SectorDetail, SectorFormMode } from '@/features/sectors/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'parent_id', 'is_active'] as const

export type SectorFormValues = CreateSectorFormValues

interface UseSectorFormArgs {
  mode: SectorFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (sector: SectorDetail) => void
}

/**
 * Owns every non-render concern of `SectorForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useSectorForm({ mode, onSuccess }: UseSectorFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'sectors',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.sector.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateSectorSchema(t, customFields.schema)
        : buildCreateSectorSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<SectorFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.sector.name,
        parent_id: mode.sector.parent_id,
        is_active: mode.sector.is_active,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      parent_id: mode.parentId,
      is_active: true,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  const form = useForm<SectorFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: SectorFormValues) => {
    setServerError(null)
    const errorFields: Path<SectorFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<SectorFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateSector(mode.sector.id, buildUpdatePayload(values, mode.sector))
        queryClient.setQueryData(sectorKeys.detail(mode.sector.id), saved)
        void queryClient.invalidateQueries({ queryKey: sectorKeys.tree })
        toast.success(t('sectors.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createSector(buildCreatePayload(values))
      void queryClient.invalidateQueries({ queryKey: sectorKeys.tree })
      toast.success(t('sectors.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('sectors.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
