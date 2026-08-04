import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Input } from '@/components/ui/input'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import {
  MANAGER_LABEL_MAX_LENGTH,
  MANAGER_LABEL_POSITIONS,
} from '@/features/product-categories/product-category-schema'
import type { ManagerLabels } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ManagerLabelsInheritanceToggleProps {
  control: Control<ProductCategoryFormValues>
}

/**
 * The manager-labels section's "inherit from parent" switch (spec 0080), same
 * barrier pattern as the attribute contexts (`InheritanceToggle` in
 * `product-category-form-body.tsx`) but gated by the single `manager_labels`
 * permission field — the section has no per-context split to give it two.
 */
export function ManagerLabelsInheritanceToggle({ control }: ManagerLabelsInheritanceToggleProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="inherits_manager_labels"
      metaKey="manager_labels"
      label={t('productCategories.form.inheritsManagerLabels')}
      description={<FormDescription>{t('productCategories.form.inheritsManagerLabelsHint')}</FormDescription>}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

interface ManagerLabelEditorProps {
  /** Always carries all four positions as keys (blank rows included) — the form's controlled-input shape. */
  value: ManagerLabels
  onChange: (next: ManagerLabels) => void
  /** Read-only, resolved from the selected parent's ancestry; empty while the toggle is off or the category is a root. */
  inherited: ManagerLabels
  /** The "inherit from parent" switch, provided by the form; null on a root category. */
  inheritToggle?: ReactNode
  disabled?: boolean
}

/**
 * The category form's manager-labels section (spec 0080): one row per G.A.
 * level (1..4), each an optional label overriding the default "Account
 * manager {{n}}" denomination, followed by a read-only preview of what the
 * category inherits from its ancestry. Mirrors `AttributeAssignmentSection`'s
 * box (`bg-surface` card, `border-t` divider before the inherited block) for
 * visual parity with the attribute sections it sits next to.
 */
export function ManagerLabelEditor({
  value,
  onChange,
  inherited,
  inheritToggle,
  disabled,
}: ManagerLabelEditorProps) {
  const { t } = useTranslation()
  const inheritedPositions = MANAGER_LABEL_POSITIONS.filter((position) => inherited[String(position)])

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-surface p-3">
      {inheritToggle}

      <div className="flex flex-col gap-2">
        {MANAGER_LABEL_POSITIONS.map((position) => {
          const key = String(position)
          const levelLabel = t('productCategories.form.managerLabelLevel', { n: position })
          return (
            <div key={position} className="flex items-center gap-2">
              <span className="w-16 shrink-0 text-xs font-medium text-muted-foreground">{levelLabel}</span>
              <Input
                className="h-8 text-sm"
                value={value[key] ?? ''}
                onChange={(event) => onChange({ ...value, [key]: event.target.value })}
                placeholder={t('productCategories.form.managerLabelPlaceholder', { n: position })}
                maxLength={MANAGER_LABEL_MAX_LENGTH}
                disabled={disabled}
                aria-label={levelLabel}
              />
            </div>
          )
        })}
      </div>

      {inheritedPositions.length > 0 && (
        <div className="mt-1 flex flex-col gap-1.5 border-t pt-3">
          <p className="text-xs font-medium text-muted-foreground">
            {t('productCategories.form.inheritedManagerLabels')}
          </p>
          <ul className="flex flex-col gap-1.5">
            {inheritedPositions.map((position) => (
              <li
                key={position}
                className="flex items-center gap-2 rounded-md bg-muted/50 px-2 py-1.5 text-sm text-muted-foreground"
              >
                <span className="w-16 shrink-0 text-xs font-medium">
                  {t('productCategories.form.managerLabelLevel', { n: position })}
                </span>
                <span className="min-w-0 flex-1 truncate">{inherited[String(position)]}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
