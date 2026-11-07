import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import type { CompetenceCategoryAge, CompetenceLineRow } from '@/features/product-lines/types'

const AGES: ReadonlyArray<{ key: CompetenceCategoryAge; label: string; ariaLabel: string }> = [
  { key: 'covers_current_categories', label: 'productLines.currentCategoriesShort', ariaLabel: 'productLines.currentCategories' },
  { key: 'covers_legacy_categories', label: 'productLines.legacyCategoriesShort', ariaLabel: 'productLines.legacyCategories' },
]

interface CompetenceCategoryAgeProps {
  row: CompetenceLineRow
  index: number
  disabled: boolean
  onChange: (age: CompetenceCategoryAge, checked: boolean) => void
}

/**
 * Spec 0367: on an "all categories" competence row, which half of the
 * function's catalogue it keeps — "Nuove", "Vecchie" ([OLD]) or both. The
 * last checked box cannot be cleared: a row keeping neither covers nothing.
 */
export function CompetenceCategoryAgeToggles({ row, index, disabled, onChange }: CompetenceCategoryAgeProps) {
  const { t } = useTranslation()
  const checked = (key: CompetenceCategoryAge): boolean => row[key] !== false
  const checkedCount = AGES.filter((age) => checked(age.key)).length

  return (
    <div className="flex shrink-0 items-center gap-2">
      {AGES.map((age) => (
        <label key={age.key} className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Checkbox
            checked={checked(age.key)}
            onCheckedChange={(value) => onChange(age.key, value === true)}
            disabled={disabled || (checked(age.key) && checkedCount === 1)}
            aria-label={t(age.ariaLabel, { n: index + 1 })}
          />
          {t(age.label)}
        </label>
      ))}
    </div>
  )
}
