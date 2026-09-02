import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProductTypology, updateProductTypology } from '@/features/product-typologies/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/product-typologies/product-typology-form-payload'
import {
  buildCreateProductTypologySchema,
  buildUpdateProductTypologySchema,
  type CreateProductTypologyFormValues,
  type UpdateProductTypologyFormValues,
} from '@/features/product-typologies/product-typology-schema'
import type {
  ProductTypologyDetail,
  ProductTypologyFormMode,
} from '@/features/product-typologies/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'code', 'description'] as const

export type ProductTypologyFormValues = CreateProductTypologyFormValues & UpdateProductTypologyFormValues

interface UseProductTypologyFormArgs {
  mode: ProductTypologyFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (productTypology: ProductTypologyDetail) => void
}

/**
 * Owns every non-render concern of `ProductTypologyForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProductTypologyForm({ mode, onSuccess }: UseProductTypologyFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateProductTypologySchema(t) : buildCreateProductTypologySchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<ProductTypologyFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.productTypology.name,
        code: mode.productTypology.code,
        description: mode.productTypology.description,
      }
    }
    return { name: '', code: '', description: null }
  }, [mode])

  const form = useForm<ProductTypologyFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ProductTypologyFormValues) => {
    setServerError(null)
    const errorFields: Path<ProductTypologyFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateProductTypology(
          mode.productTypology.id,
          buildUpdatePayload(values, mode.productTypology),
        )
        queryClient.setQueryData(['product-typologies', 'detail', mode.productTypology.id], saved)
        toast.success(t('productTypologies.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createProductTypology(buildCreatePayload(values))
      toast.success(t('productTypologies.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('productTypologies.form.genericError'))
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
