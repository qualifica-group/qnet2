import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createDocumentLayout, updateDocumentLayout } from '@/features/document-layouts/api'
import { parseConfigValidationErrors } from '@/features/document-layouts/editor/config-validation-errors'
import type { ConfigValidationError } from '@/features/document-layouts/editor/config-validation-errors'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/document-layouts/document-layout-form-payload'
import {
  buildCreateDocumentLayoutSchema,
  buildUpdateDocumentLayoutSchema,
  type CreateDocumentLayoutFormValues,
  type UpdateDocumentLayoutFormValues,
} from '@/features/document-layouts/document-layout-schema'
import type {
  DocumentLayoutDetail,
  DocumentLayoutFormMode,
} from '@/features/document-layouts/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'code', 'description', 'module', 'is_active', 'is_default'] as const

export type DocumentLayoutFormValues = CreateDocumentLayoutFormValues & UpdateDocumentLayoutFormValues

interface UseDocumentLayoutFormArgs {
  mode: DocumentLayoutFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (documentLayout: DocumentLayoutDetail) => void
}

function initialConfig(mode: DocumentLayoutFormMode): DocumentLayoutConfig {
  return mode.type === 'edit' ? mode.documentLayout.config : createEmptyDocumentLayoutConfig()
}

/**
 * Owns every non-render concern of `DocumentLayoutForm`: RHF/Zod wiring for
 * the metadata fields, the visual editor's `config` state (spec 0069 wave 2:
 * MT-7/MT-8), server 422 mapping — split between flat metadata fields and
 * `config.*` path-based errors (AC-129) — and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 * `config` is a single piece of controlled state fed straight into the save
 * payload, which is what makes the save round-trip exact (AC-128): nothing
 * here re-derives or normalizes it.
 */
export function useDocumentLayoutForm({ mode, onSuccess }: UseDocumentLayoutFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const [config, setConfig] = useState<DocumentLayoutConfig>(() => initialConfig(mode))
  const [configErrors, setConfigErrors] = useState<ConfigValidationError[]>([])

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateDocumentLayoutSchema(t) : buildCreateDocumentLayoutSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<DocumentLayoutFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.documentLayout.name,
        code: mode.documentLayout.code,
        description: mode.documentLayout.description,
        module: mode.documentLayout.module,
        is_active: mode.documentLayout.is_active,
        is_default: mode.documentLayout.is_default,
      }
    }
    return {
      name: '',
      code: '',
      description: null,
      module: 'quotes',
      is_active: true,
      is_default: false,
    }
  }, [mode])

  const form = useForm<DocumentLayoutFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: DocumentLayoutFormValues) => {
    setServerError(null)
    setConfigErrors([])
    const errorFields: Path<DocumentLayoutFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateDocumentLayout(
          mode.documentLayout.id,
          buildUpdatePayload(values, mode.documentLayout, config),
        )
        queryClient.setQueryData(['document-layouts', 'detail', mode.documentLayout.id], saved)
        toast.success(t('documentLayouts.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createDocumentLayout(buildCreatePayload(values, config))
      toast.success(t('documentLayouts.form.created'))
      onSuccess(created)
    } catch (error) {
      const rawErrors =
        axios.isAxiosError(error) && error.response?.status === 422
          ? (error.response.data?.errors as Record<string, string[]> | undefined)
          : undefined
      const parsedConfigErrors = parseConfigValidationErrors(rawErrors)
      setConfigErrors(parsedConfigErrors)

      const handledMetadata = applyServerValidationErrors(error, form.setError, errorFields)
      if (parsedConfigErrors.length > 0) {
        setServerError(t('documentLayouts.form.configValidationError'))
      } else if (!handledMetadata) {
        setServerError(t('documentLayouts.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
    config,
    onConfigChange: setConfig,
    configErrors,
  }
}
