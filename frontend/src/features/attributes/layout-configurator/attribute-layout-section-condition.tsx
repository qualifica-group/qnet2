import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { LayoutSection, LayoutSectionCondition } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutSectionConditionProps {
  section: LayoutSection
  /** Boolean attributes allowed by spec 0288 D-2 for this section. */
  options: EffectiveAttribute[]
  /** Another section depends on an attribute of this one: it cannot become conditional itself. */
  controlsOthers: boolean
  disabled: boolean
  onChange: (condition: LayoutSectionCondition | null) => void
}

/** Radix Select forbids an empty-string item value, so "always visible" gets a sentinel. */
const ALWAYS_VISIBLE = '__always__'

/**
 * "Visible only if active" selector of a section (spec 0288 D-7). Stays
 * enabled when a condition is already set so it can be changed or cleared,
 * even if no other option remains.
 */
export function AttributeLayoutSectionCondition({
  section,
  options,
  controlsOthers,
  disabled,
  onChange,
}: AttributeLayoutSectionConditionProps) {
  const { t } = useTranslation('attributeLayout')
  const selectId = `attribute-layout-section-condition-${section.id}`
  const current = section.visible_when?.attribute_code ?? null
  const blocked = current === null && (controlsOthers || options.length === 0)
  const helpKey = controlsOthers ? 'conditionControlsOthersHelp' : 'conditionNoOptionsHelp'
  // A saved control no longer in the options (renamed/removed upstream) still needs an item to display
  const items = current !== null && !options.some((option) => option.code === current) ? [{ code: current, name: current }] : options

  return (
    <div className="flex flex-col gap-0.5">
      <div className="flex items-center gap-1.5">
        <Label htmlFor={selectId} className="text-xs font-normal text-muted-foreground">
          {t('configurator.sectionConditionLabel')}
        </Label>
        <Select
          value={current ?? ALWAYS_VISIBLE}
          onValueChange={(value) => onChange(value === ALWAYS_VISIBLE ? null : { attribute_code: value })}
          disabled={disabled || blocked}
        >
          <SelectTrigger id={selectId} size="sm" className="h-7 w-44 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALWAYS_VISIBLE}>{t('configurator.sectionConditionAlways')}</SelectItem>
            {items.map((item) => (
              <SelectItem key={item.code} value={item.code}>
                {item.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {blocked ? <p className="text-xs text-muted-foreground">{t(`configurator.${helpKey}`)}</p> : null}
    </div>
  )
}
