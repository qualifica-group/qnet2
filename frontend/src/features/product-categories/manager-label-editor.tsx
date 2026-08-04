import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Plus, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import {
  MANAGER_LABEL_MAX_LENGTH,
  nextFreeManagerLabelPosition,
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

/** The G.A. positions a `ManagerLabels` map carries, ascending — shared by the editable rows and the inherited preview. */
function sortedPositions(labels: ManagerLabels): number[] {
  return Object.keys(labels)
    .map(Number)
    .sort((a, b) => a - b)
}

interface ManagerLabelEditorProps {
  /**
   * The rows currently shown, keyed by G.A. position — blank values included.
   * Which positions are present IS the row list (spec 0080 A1): "Add level"
   * inserts the next free one, "Reset" removes its key entirely.
   */
  value: ManagerLabels
  onChange: (next: ManagerLabels) => void
  /** Read-only, resolved from the selected parent's ancestry; empty while the toggle is off or the category is a root. */
  inherited: ManagerLabels
  /** The "inherit from parent" switch, provided by the form; null on a root category. */
  inheritToggle?: ReactNode
  disabled?: boolean
}

/**
 * The category form's manager-labels section (spec 0080, amendment A1): a
 * DYNAMIC list of G.A. rows — opens with the category's own positions padded
 * to a reasonable minimum (`toManagerLabelsFormValue`), "Add level" appends
 * the next free position up to the safety ceiling, "Reset" on a row only
 * clears that position's OWN label (the level falls back to the default
 * denomination — it never touches an assigned account manager or
 * `manager_slots`). Followed by a read-only preview of what the category
 * inherits from its ancestry. Mirrors `AttributeAssignmentSection`'s box
 * (`bg-surface` card, `border-t` divider before the inherited block) for
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
  const rows = useMemo(() => sortedPositions(value), [value])
  const inheritedRows = useMemo(
    () => sortedPositions(inherited).filter((position) => inherited[String(position)]),
    [inherited],
  )
  const nextPosition = nextFreeManagerLabelPosition(rows)

  const handleAdd = () => {
    if (nextPosition === null) {
      return
    }
    onChange({ ...value, [String(nextPosition)]: '' })
  }

  const handleReset = (position: number) => {
    const key = String(position)
    onChange(Object.fromEntries(Object.entries(value).filter(([entryKey]) => entryKey !== key)))
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-surface p-3">
      {inheritToggle}

      <p className="text-xs text-muted-foreground">{t('productCategories.form.managerLabelsHelp')}</p>

      <div className="flex flex-col gap-2">
        {rows.map((position) => {
          const key = String(position)
          const levelLabel = t('productCategories.form.managerLabelLevel', { n: position })
          return (
            <div key={position} className="flex items-center gap-2">
              <span className="w-16 shrink-0 text-xs font-medium text-muted-foreground">{levelLabel}</span>
              <Input
                className="h-8 flex-1 text-sm"
                value={value[key] ?? ''}
                onChange={(event) => onChange({ ...value, [key]: event.target.value })}
                placeholder={t('productCategories.form.managerLabelPlaceholder', { n: position })}
                maxLength={MANAGER_LABEL_MAX_LENGTH}
                disabled={disabled}
                aria-label={levelLabel}
              />
              {!disabled && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  aria-label={t('productCategories.form.resetManagerLabelLevel', { n: position })}
                  onClick={() => handleReset(position)}
                >
                  <RotateCcw aria-hidden="true" />
                </Button>
              )}
            </div>
          )
        })}
      </div>

      {!disabled && (
        <Button
          type="button"
          variant="outline"
          size="xs"
          className="self-start bg-card"
          onClick={handleAdd}
          disabled={nextPosition === null}
        >
          <Plus aria-hidden="true" />
          {t('productCategories.form.addManagerLabelLevel')}
        </Button>
      )}

      {inheritedRows.length > 0 && (
        <div className="mt-1 flex flex-col gap-1.5 border-t pt-3">
          <p className="text-xs font-medium text-muted-foreground">
            {t('productCategories.form.inheritedManagerLabels')}
          </p>
          <ul className="flex flex-col gap-1.5">
            {inheritedRows.map((position) => (
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
