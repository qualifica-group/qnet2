import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailSection } from '@/components/detail/detail-panel'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import '@/features/attributes/layout-configurator/i18n'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { AttributeLayoutFormShape, LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import { useAttributeLayout } from '@/features/product-categories/use-attribute-layout'
import { AttributeLayoutContextModeSelector } from '@/features/product-categories/product-category-attribute-layout-context-mode-selector'
import {
  buildAttributeLayoutLabels,
  previewModeForScope,
} from '@/features/product-categories/product-category-attribute-layout-shared'
import type { AttributeContext } from '@/features/product-categories/types'

interface ProductCategoryAttributeLayoutPreviewProps {
  categoryId: number
}

/**
 * Read-only counterpart of `ProductCategoryAttributeLayoutEditor` (spec
 * 0062): authoring/Save moved to the edit form, so the detail view only
 * renders the same (context × scope) selector feeding the resolved layout
 * into the structural `AttributeLayoutRenderer` (`readOnly`), backed by a
 * throwaway local RHF form — no submit, no real product values, purely
 * structural. A per-mode scope with no override shows the shared layout it
 * inherits, flagged as such (D3 revised). Deliberately skips the renderer's
 * own flat-fallback: when a combination resolves to nothing, an explicit
 * empty state is shown instead.
 */
export function ProductCategoryAttributeLayoutPreview({ categoryId }: ProductCategoryAttributeLayoutPreviewProps) {
  const { t } = useTranslation('attributeLayout')
  const [context, setContext] = useState<AttributeContext>('product')
  const [scope, setScope] = useState<LayoutFormScope>('all')
  const previewForm = useForm<AttributeLayoutFormShape>({ defaultValues: { attribute_values: {} } })

  const { attributes, draft, hasOverride, isLoading, isError, refetch } = useAttributeLayout({
    categoryId,
    context,
    scope,
    labels: buildAttributeLayoutLabels(t),
  })

  return (
    <DetailSection title={t('section.title')}>
      <p className="mb-3 text-xs text-muted-foreground">{t('section.description')}</p>

      <div className="flex flex-col gap-3">
        <AttributeLayoutContextModeSelector
          context={context}
          onContextChange={setContext}
          scope={scope}
          onScopeChange={setScope}
        />

        {scope !== 'all' && !hasOverride && draft.sections.length > 0 ? (
          <p className="text-xs text-muted-foreground italic">{t('section.inheritsShared')}</p>
        ) : null}

        {isLoading ? (
          <div className="flex flex-col gap-1.5" aria-hidden="true">
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : isError ? (
          <div className="flex flex-col items-start gap-3">
            <p className="text-sm text-destructive" role="alert">
              {t('section.loadError')}
            </p>
            <Button variant="outline" size="sm" onClick={() => void refetch()}>
              {t('section.retry')}
            </Button>
          </div>
        ) : draft.sections.length === 0 ? (
          <p className="text-xs text-muted-foreground italic">{t('section.previewEmpty')}</p>
        ) : (
          <Form {...previewForm}>
            <form>
              <AttributeLayoutRenderer
                layout={draft}
                attributes={attributes}
                control={previewForm.control}
                mode={previewModeForScope(scope)}
                readOnly
              />
            </form>
          </Form>
        )}
      </div>
    </DetailSection>
  )
}
