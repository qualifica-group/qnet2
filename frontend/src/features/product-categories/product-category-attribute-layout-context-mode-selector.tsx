import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import {
  ATTRIBUTE_LAYOUT_CONTEXTS,
  ATTRIBUTE_LAYOUT_FORM_MODES,
} from '@/features/product-categories/product-category-attribute-layout-shared'
import type { LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import type { AttributeContext } from '@/features/product-categories/types'

interface AttributeLayoutContextModeSelectorProps {
  context: AttributeContext
  onContextChange: (context: AttributeContext) => void
  formMode: LayoutFormMode
  onFormModeChange: (formMode: LayoutFormMode) => void
  /** Right-aligned slot, e.g. the editor's Save button. Omitted in the read-only preview. */
  trailing?: ReactNode
}

/**
 * The (context × form_mode) picker shared by the attribute-layout EDITOR
 * (form) and its read-only PREVIEW (detail) — spec 0062: each combination is
 * an entirely separate `attribute_layouts` row, so switching either
 * dimension changes what is loaded underneath.
 */
export function AttributeLayoutContextModeSelector({
  context,
  onContextChange,
  formMode,
  onFormModeChange,
  trailing,
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
        <Select value={formMode} onValueChange={(value) => onFormModeChange(value as LayoutFormMode)}>
          <SelectTrigger id="attribute-layout-form-mode" size="sm" className="h-8 w-36 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {ATTRIBUTE_LAYOUT_FORM_MODES.map((value) => (
              <SelectItem key={value} value={value}>
                {t(`section.mode.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {trailing ? <div className="ml-auto flex items-center gap-2">{trailing}</div> : null}
    </div>
  )
}
