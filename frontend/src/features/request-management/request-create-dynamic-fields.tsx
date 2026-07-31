import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, SlidersHorizontal } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface RequestCreateDynamicFieldsProps {
  control: Control<RequestCreateFormValues>
  /** Union, dedup by `code`, of the effective attributes of the chosen categories — resolved live by `POST /request-management/form-context`. */
  attributes: ApplicableAttribute[]
  /** The merged, multi-category layout for `form_mode = create` (spec 0062); `null` -> flat. */
  layout: LayoutBlob | null
  /** True while the first resolution for the current categories is in flight. */
  isLoading: boolean
}

/**
 * The create form's dynamic fields (user directive 2026-07-31): the SAME
 * `AttributeLayoutRenderer` the work panel drives, fed the set the chosen
 * categories resolve to — so a request is opened with its category-specific
 * information already captured instead of waiting for the first save.
 *
 * Twin of `RequestDynamicFields` rather than a shared generic component,
 * following this module's own convention (`RequestCreate*` twins for the
 * attribution, the anagrafica and the products of interest): the panel binds
 * through `MetaField` against a server-derived `permissions` envelope, which a
 * create-only form does not have — creation is gated wholesale by
 * `request-management.create`.
 *
 * Renders nothing at all until a categoria prodotto is chosen: with no
 * category there is no applicable set, and an "empty" card would read as "this
 * request has no additional fields" when the truth is "you have not told me
 * which ones yet".
 */
export function RequestCreateDynamicFields({
  control,
  attributes,
  layout,
  isLoading,
}: RequestCreateDynamicFieldsProps) {
  const { t } = useTranslation()
  const title = t('requestManagement.workPanel.dynamicFields.title', { defaultValue: 'Additional information' })

  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute)),
    [attributes],
  )

  if (isLoading) {
    return (
      <FormSection icon={SlidersHorizontal} title={title}>
        <p className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
          {t('common.loading')}
        </p>
      </FormSection>
    )
  }

  if (attributes.length === 0) {
    return null
  }

  const renderer = (
    <AttributeLayoutRenderer
      layout={layout}
      attributes={effectiveAttributes}
      control={control}
      mode="edit"
    />
  )

  // A configured layout's sections already carry their own card, so nesting
  // them inside a FormSection would stack two cards on the same surface
  // (ui-design.md §1-bis) — identical rule to the panel's own.
  if (layout && layout.sections.length > 0) {
    return renderer
  }

  return (
    <FormSection icon={SlidersHorizontal} title={title}>
      {renderer}
    </FormSection>
  )
}
