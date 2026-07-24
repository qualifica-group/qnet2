import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Skeleton } from '@/components/ui/skeleton'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { LayoutBlob, LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

interface ProductDynamicFieldsProps {
  control: Control<ProductFormValues>
  /** The selected category's PRODUCT-context effective attributes (own + inherited), spec 0061. */
  attributes: EffectiveAttribute[]
  /** The selected category's configured PRODUCT-context layout for this form mode, spec 0062; `null` -> flat. */
  layout: LayoutBlob | null
  /** `'create'`/`'edit'` — the form's own mode (D3: independent layouts), never `'view'` here. */
  mode: LayoutFormMode
  isLoading: boolean
  /**
   * Resource-level gate: attribute values are authorized at the
   * `products.update`/`products.create` ability, not per field (mirrors
   * `useProductFormMeta`'s documented design — there is no per-attribute
   * entry in `/meta/products`, unlike the generic fields above it).
   */
  disabled: boolean
}

/**
 * The product form's dynamic-fields section: rendered through the
 * module-agnostic `AttributeLayoutRenderer` (spec 0062). No configured
 * layout -> the SAME `FormSection` wrapper (icon/title) as before, hosting
 * the renderer's flat fallback — byte-for-byte the prior `ProductDynamicField`
 * markup (AC-007). A configured layout renders its sections directly: each
 * section already carries its own card chrome (`ConfigSection`), so nesting
 * it inside another `bg-card` `FormSection` would stack two cards on the same
 * surface (ui-design.md §1-bis) — this wrapper is skipped in that branch.
 */
export function ProductDynamicFields({ control, attributes, layout, mode, isLoading, disabled }: ProductDynamicFieldsProps) {
  const { t } = useTranslation()
  const title = t('products.form.dynamicFields.title')

  if (isLoading) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <Skeleton className="h-9 w-full" />
      </FormSection>
    )
  }

  if (attributes.length === 0) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <p className="text-sm text-muted-foreground">{t('products.form.dynamicFields.empty')}</p>
      </FormSection>
    )
  }

  if (!layout || layout.sections.length === 0) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <AttributeLayoutRenderer
          layout={null}
          attributes={attributes}
          control={control}
          mode={mode}
          disabled={disabled}
        />
      </FormSection>
    )
  }

  return (
    <AttributeLayoutRenderer
      layout={layout}
      attributes={attributes}
      control={control}
      mode={mode}
      disabled={disabled}
    />
  )
}
