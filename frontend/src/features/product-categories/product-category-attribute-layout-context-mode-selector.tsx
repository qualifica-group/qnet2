import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import {
  ATTRIBUTE_LAYOUT_CONTEXTS,
  ATTRIBUTE_LAYOUT_FORM_SCOPES,
} from '@/features/product-categories/product-category-attribute-layout-shared'
import type { LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import type { AttributeContext } from '@/features/product-categories/types'

interface AttributeLayoutContextModeSelectorProps {
  context: AttributeContext
  onContextChange: (context: AttributeContext) => void
  scope: LayoutFormScope
  onScopeChange: (scope: LayoutFormScope) => void
}

/**
 * The (context × scope) picker shared by the attribute-layout EDITOR (sheet)
 * and its read-only PREVIEW (detail) — spec 0062, D3 revised: each
 * combination is an entirely separate `attribute_layouts` row, so switching
 * either dimension changes what is loaded underneath. The scope list leads
 * with "all modes", the shared layout every mode falls back to.
 */
export function AttributeLayoutContextModeSelector({
  context,
  onContextChange,
  scope,
  onScopeChange,
}: AttributeLayoutContextModeSelectorProps) {
  const { t } = useTranslation('attributeLayout')

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Tabs value={context} onValueChange={(value) => onContextChange(value as AttributeContext)}>
        <TabsList className={FORM_TAB_LIST_CLASS} aria-label={t('section.contextLabel')}>
          {ATTRIBUTE_LAYOUT_CONTEXTS.map((value) => (
            <TabsTrigger key={value} value={value} className={FORM_TAB_TRIGGER_CLASS}>
              {t(`section.context.${value}`)}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      <div className="flex items-center gap-1.5">
        <Label htmlFor="attribute-layout-form-mode" className="text-xs font-normal text-muted-foreground">
          {t('section.modeLabel')}
        </Label>
        <Select value={scope} onValueChange={(value) => onScopeChange(value as LayoutFormScope)}>
          <SelectTrigger id="attribute-layout-form-mode" size="sm" className="h-8 w-44 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {ATTRIBUTE_LAYOUT_FORM_SCOPES.map((value) => (
              <SelectItem key={value} value={value}>
                {t(`section.mode.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </div>
  )
}
