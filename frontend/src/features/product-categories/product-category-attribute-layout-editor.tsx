import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { LayoutGrid } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import '@/features/attributes/layout-configurator/i18n'
import { AttributeLayoutConfigurator } from '@/features/attributes/layout-configurator/attribute-layout-configurator'
import type { LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import { useAttributeLayout } from '@/features/product-categories/use-attribute-layout'
import { AttributeLayoutContextModeSelector } from '@/features/product-categories/product-category-attribute-layout-context-mode-selector'
import { buildAttributeLayoutLabels } from '@/features/product-categories/product-category-attribute-layout-shared'
import type { AttributeContext } from '@/features/product-categories/types'

interface ProductCategoryAttributeLayoutEditorProps {
  categoryId: number
  /** `product-categories.update` resolved for THIS instance (spec 0004: `permissions.resource.update`). */
  canEdit: boolean
}

/**
 * MT-3.2 mount (spec 0062), relocated from the read-only category detail to
 * the EDIT form (`ProductCategoryFormBody`, edit mode only — a category must
 * exist before its layout can be authored). A (context × form_mode)
 * selector loads/saves its own independent layout — 6 combinations, each an
 * entirely separate `attribute_layouts` row — hosting the drag-and-drop
 * configurator (`AttributeLayoutConfigurator`, MT-3.1) plus its live
 * preview. Its Save is an independent `PUT`, unrelated to the category
 * form's own submit — the caller mounts this OUTSIDE the RHF `<form>` so its
 * button never doubles as a submit trigger.
 */
export function ProductCategoryAttributeLayoutEditor({
  categoryId,
  canEdit,
}: ProductCategoryAttributeLayoutEditorProps) {
  const { t } = useTranslation('attributeLayout')
  const [context, setContext] = useState<AttributeContext>('product')
  const [formMode, setFormMode] = useState<LayoutFormMode>('create')

  const { attributes, draft, setDraft, isLoading, isError, refetch, isSaving, error, save } = useAttributeLayout({
    categoryId,
    context,
    formMode,
    labels: buildAttributeLayoutLabels(t),
  })

  return (
    <FormSection icon={LayoutGrid} title={t('section.title')} description={t('section.description')}>
      <p className="text-xs text-muted-foreground">{t('section.editorHint')}</p>

      <AttributeLayoutContextModeSelector
        context={context}
        onContextChange={setContext}
        formMode={formMode}
        onFormModeChange={setFormMode}
        trailing={
          canEdit ? (
            <Button
              type="button"
              size="sm"
              disabled={isLoading || isError || isSaving}
              onClick={() => void save()}
            >
              {isSaving ? t('section.saving') : t('section.save')}
            </Button>
          ) : null
        }
      />

      {error ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
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
      ) : (
        <>
          {draft.sections.length === 0 ? (
            <p className="text-xs text-muted-foreground italic">{t('section.empty')}</p>
          ) : null}
          <AttributeLayoutConfigurator
            blob={draft}
            onChange={setDraft}
            attributes={attributes}
            mode={formMode}
            disabled={!canEdit || isSaving}
          />
        </>
      )}
    </FormSection>
  )
}
