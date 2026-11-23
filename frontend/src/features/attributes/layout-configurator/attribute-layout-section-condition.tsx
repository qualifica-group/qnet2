import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { LayoutSection, LayoutSectionCondition } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutSectionConditionProps {
  section: LayoutSection
  /** Boolean and choice attributes allowed by spec 0288 D-2 / 0424 D-2 for this section. */
  options: EffectiveAttribute[]
  /** Another section depends on an attribute of this one: it cannot become conditional itself. */
  controlsOthers: boolean
  disabled: boolean
  onChange: (condition: LayoutSectionCondition | null) => void
}

interface ConditionItem {
  key: string
  label: string
  condition: LayoutSectionCondition
}

/** Radix Select forbids an empty-string item value, so "always visible" gets a sentinel. */
const ALWAYS_VISIBLE = '__always__'

/** A stable Select value for a condition: attribute codes and option values may hold any character. */
function conditionKey(condition: LayoutSectionCondition): string {
  return JSON.stringify([condition.attribute_code, condition.option_value ?? null])
}

/** One item per yes/no attribute, one per option of a choice attribute ("Name: Option"). */
function conditionItems(attributes: EffectiveAttribute[]): ConditionItem[] {
  return attributes.flatMap((attribute) => {
    if (attribute.type !== 'enum') {
      const condition = { attribute_code: attribute.code }
      return [{ key: conditionKey(condition), label: attribute.name, condition }]
    }
    return attribute.options.map((option) => {
      const condition = { attribute_code: attribute.code, option_value: option.value }
      return { key: conditionKey(condition), label: `${attribute.name}: ${option.label}`, condition }
    })
  })
}

/**
 * "Visible only if active" selector of a section (spec 0288 D-7, 0424 D-3).
 * Stays enabled when a condition is already set so it can be changed or
 * cleared, even if no other option remains.
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
  const current = section.visible_when ?? null
  const blocked = current === null && (controlsOthers || options.length === 0)
  const helpKey = controlsOthers ? 'conditionControlsOthersHelp' : 'conditionNoOptionsHelp'
  const available = conditionItems(options)
  const currentKey = current === null ? ALWAYS_VISIBLE : conditionKey(current)
  // A saved control no longer offered (renamed/removed upstream) still needs an item to display
  const items =
    current !== null && !available.some((item) => item.key === currentKey)
      ? [...available, { key: currentKey, label: [current.attribute_code, current.option_value].filter(Boolean).join(': '), condition: current }]
      : available

  return (
    <div className="flex flex-col gap-0.5">
      <div className="flex items-center gap-1.5">
        <Label htmlFor={selectId} className="text-xs font-normal text-muted-foreground">
          {t('configurator.sectionConditionLabel')}
        </Label>
        <Select
          value={currentKey}
          onValueChange={(value) =>
            onChange(value === ALWAYS_VISIBLE ? null : (items.find((item) => item.key === value)?.condition ?? null))
          }
          disabled={disabled || blocked}
        >
          <SelectTrigger id={selectId} size="sm" className="h-7 w-44 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALWAYS_VISIBLE}>{t('configurator.sectionConditionAlways')}</SelectItem>
            {items.map((item) => (
              <SelectItem key={item.key} value={item.key}>
                {item.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {blocked ? <p className="text-xs text-muted-foreground">{t(`configurator.${helpKey}`)}</p> : null}
    </div>
  )
}
