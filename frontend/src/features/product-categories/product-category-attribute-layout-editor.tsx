import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { LayoutGrid } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import '@/features/attributes/layout-configurator/i18n'
import { AttributeLayoutConfigurator } from '@/features/attributes/layout-configurator/attribute-layout-configurator'
import type { LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import { useAttributeLayout } from '@/features/product-categories/use-attribute-layout'
import { AttributeLayoutContextModeSelector } from '@/features/product-categories/product-category-attribute-layout-context-mode-selector'
import { AttributeLayoutScopeNotice } from '@/features/product-categories/product-category-attribute-layout-scope-notice'
import {
  buildAttributeLayoutLabels,
  previewModeForScope,
} from '@/features/product-categories/product-category-attribute-layout-shared'
import type { AttributeContext } from '@/features/product-categories/types'

interface ProductCategoryAttributeLayoutEditorProps {
  categoryId: number
  /** Discards the unsaved local draft and closes the host Sheet (footer "Cancel"). */
  onCancel: () => void
}

/**
 * MT-3.2 mount, relocated (spec 0062 revision) from the category form to a
 * dedicated Sheet opened by the "layout" row action on the Product
 * Categories table (`ProductCategoryAttributeLayoutSheet`) — the row action
 * only appears for rows the actor can update, and the underlying `PUT` is
 * authorized server-side regardless, so this component always renders its
 * Save. A (context × scope) selector loads/saves its own independent layout,
 * hosting the drag-and-drop configurator (`AttributeLayoutConfigurator`,
 * MT-3.1) plus its live preview.
 *
 * Scope (spec 0062, D3 revised): the default "all modes" scope is the shared
 * layout driving create/edit/view together. Picking a single mode shows what
 * that mode currently inherits, READ-ONLY, until the notice's "customize"
 * action turns it into an override — so the configurator is only editable on
 * the scope actually being authored, and Save can never silently mint an
 * override the actor did not ask for.
 *
 * Wraps its scrollable body in the `bg-card` `FormSection` (ui-design.md
 * §1-bis rung 3) the configurator's own sub-blocks (palette/preview/section
 * editor, `features/attributes/layout-configurator/`) expect as their host —
 * the Sheet's `SheetContent` is only `bg-background` (rung 1), so without
 * this card their `bg-muted/40` tint would sit directly on the page surface.
 *
 * Owns the full Sheet body: a scrollable area plus a footer Save/Cancel bar,
 * aligned with every other Sheet with an explicit save
 * (`default-statuses-sheet.tsx`). Save deliberately does NOT close the host
 * Sheet on success (unlike that reference): this editor hosts 6 independent
 * (context × form_mode) combinations an actor commonly edits one after
 * another in the same visit, so the existing success toast is the only
 * confirmation. Cancel discards the unsaved local draft and closes the Sheet.
 */
export function ProductCategoryAttributeLayoutEditor({
  categoryId,
  onCancel,
}: ProductCategoryAttributeLayoutEditorProps) {
  const { t } = useTranslation('attributeLayout')
  const [context, setContext] = useState<AttributeContext>('product')
  const [scope, setScope] = useState<LayoutFormScope>('all')

  const {
    attributes,
    draft,
    setDraft,
    hasOverride,
    isCustomizing,
    inheritedFromCategory,
    customize,
    resetToShared,
    isLoading,
    isError,
    refetch,
    isSaving,
    error,
    save,
  } = useAttributeLayout({
    categoryId,
    context,
    scope,
    labels: buildAttributeLayoutLabels(t),
  })

  return (
    <div className="flex flex-1 flex-col overflow-hidden">
      <div className="flex-1 overflow-y-auto p-4">
        <FormSection icon={LayoutGrid} title={t('section.title')} description={t('section.editorHint')}>
          <AttributeLayoutContextModeSelector
            context={context}
            onContextChange={setContext}
            scope={scope}
            onScopeChange={setScope}
          />

          {isLoading || isError ? null : (
            <AttributeLayoutScopeNotice
              scope={scope}
              hasOverride={hasOverride}
              isCustomizing={isCustomizing}
              inheritedFromCategory={inheritedFromCategory}
              disabled={isSaving}
              onCustomize={customize}
              onResetToShared={() => void resetToShared()}
            />
          )}

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
                mode={previewModeForScope(scope)}
                disabled={isSaving || !isCustomizing}
              />
            </>
          )}
        </FormSection>
      </div>

      <div className="flex justify-end gap-2 border-t p-4">
        <Button variant="outline" onClick={onCancel} disabled={isSaving}>
          {t('section.cancel')}
        </Button>
        <Button disabled={isLoading || isError || isSaving || !isCustomizing} onClick={() => void save()}>
          {isSaving ? t('section.saving') : t('section.save')}
        </Button>
      </div>
    </div>
  )
}
