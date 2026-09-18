import type { Control } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  SearchableMultiSelect,
  type SearchableMultiSelectLabels,
} from '@/components/ui/searchable-multi-select'
import type { RequestReportFormValues } from '@/features/request-management/request-report-schema'

/** Suffix shared by each picker's "select all" and "all selected" translation keys. */
export type PickerGroup = 'Categories' | 'Sites' | 'Operators'

type KeyFieldName = 'category_keys' | 'site_keys' | 'operator_keys'

export interface KeySelectFieldProps {
  control: Control<RequestReportFormValues>
  name: KeyFieldName
  /** Field label (already translated). */
  label: string
  labels: SearchableMultiSelectLabels
  /** Entries as the report endpoints serve them; labels are domain values, rendered as-is. */
  items: { key: string; label: string; parent_key?: string | null }[]
  /** Replaces the plain field write when a change has to ripple to another field. */
  onChange?: (next: string[]) => void
  /** Shown under the control, e.g. why the list is narrower than usual. */
  hint?: string
  disabled: boolean
}

/**
 * One report key group (branches, Sedi, GA2) as a searchable multi-select
 * bound to its form field: label, picker and the accessible error triad that
 * `FormControl` wires onto the trigger. Replaces the always-open checkbox
 * cards (user directive 2026-09-18) with the same tri-state "select all".
 */
export function KeySelectField({ control, name, label, labels, items, onChange, hint, disabled }: KeySelectFieldProps) {
  // A `parent_key` makes the list a tree (the report categories): indented,
  // and a parent ticks its whole subtree.
  const options = items.map((item) => ({ value: item.key, label: item.label, parentValue: item.parent_key ?? null }))

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1.5">
          <FormLabel required className="text-xs font-medium">
            {label}
          </FormLabel>
          <FormControl>
            <SearchableMultiSelect
              options={options}
              value={field.value}
              onChange={onChange ?? field.onChange}
              labels={labels}
              disabled={disabled}
            />
          </FormControl>
          {hint ? <FormDescription className="text-xs">{hint}</FormDescription> : null}
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
