import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { MultiSelect } from '@/components/ui/multi-select'
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

/** Radix Select forbids an empty-string item value, so "always visible" gets a sentinel. */
const ALWAYS_VISIBLE = '__always__'

const NO_OPTIONS: string[] = []

/** A yes/no control opens the section by itself; a choice control needs the entries that open it. */
function conditionFor(attribute: EffectiveAttribute | undefined, code: string): LayoutSectionCondition {
  return attribute?.type === 'enum' ? { attribute_code: code, option_values: [] } : { attribute_code: code }
}

/**
 * "Visible only if active" selector of a section (spec 0288 D-7, 0424 D-3):
 * the control attribute, then — for a choice attribute — the entries that
 * open the section (any of them). Stays enabled when a condition is already
 * set so it can be changed or cleared, even if no other option remains.
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
  const control = current ? options.find((option) => option.code === current.attribute_code) : undefined
  // A saved control no longer offered (renamed/removed upstream) still needs an item to display
  const items =
    current !== null && control === undefined ? [...options, { code: current.attribute_code, name: current.attribute_code }] : options
  const isChoice = current !== null && (control?.type === 'enum' || (current.option_values?.length ?? 0) > 0)
  const selectedOptions = current?.option_values ?? NO_OPTIONS
  const choiceOptions = (control?.options ?? []).map((option) => ({ value: option.value, label: option.label }))

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-1.5">
        <Label htmlFor={selectId} className="text-xs font-normal text-muted-foreground">
          {t('configurator.sectionConditionLabel')}
        </Label>
        <Select
          value={current?.attribute_code ?? ALWAYS_VISIBLE}
          onValueChange={(code) =>
            onChange(code === ALWAYS_VISIBLE ? null : conditionFor(options.find((option) => option.code === code), code))
          }
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
      {isChoice && current !== null ? (
        <div className="flex flex-col gap-0.5">
          <span className="text-xs text-muted-foreground">{t('configurator.sectionConditionOptionsLabel')}</span>
          <MultiSelect
            options={choiceOptions}
            value={selectedOptions}
            // An entry since removed from the attribute is invisible here: drop it on the next change
            onChange={(values) =>
              onChange({
                attribute_code: current.attribute_code,
                option_values: values.filter((value) => choiceOptions.some((option) => option.value === value)),
              })
            }
            placeholder={t('configurator.sectionConditionOptionsPlaceholder')}
            disabled={disabled}
            aria-label={t('configurator.sectionConditionOptionsLabel')}
            className="w-full max-w-xs"
          />
        </div>
      ) : null}
      {blocked ? <p className="text-xs text-muted-foreground">{t(`configurator.${helpKey}`)}</p> : null}
    </div>
  )
}
