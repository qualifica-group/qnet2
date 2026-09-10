import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type {
  Control,
  ControllerRenderProps,
  FieldPath,
  FieldValues,
} from 'react-hook-form'
import {
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { FieldHint } from '@/components/field-hint'
import { useResourcePermissions } from '@/features/authorization/permissions'

interface MetaFieldRenderArgs<
  TFieldValues extends FieldValues,
  TName extends FieldPath<TFieldValues>,
> {
  field: ControllerRenderProps<TFieldValues, TName>
  /** Forward to the input's `disabled` prop: true when hard-disabled or non-editable. */
  disabled: boolean
  /** Forward to the input's `readOnly` prop when it supports one (e.g. text inputs). */
  readOnly: boolean
}

interface MetaFieldProps<
  TFieldValues extends FieldValues,
  TName extends FieldPath<TFieldValues>,
> {
  control: Control<TFieldValues>
  name: TName
  /** Authorization metadata key for this field (may differ from the RHF path). */
  metaKey: string
  label: ReactNode
  /** Overrides the automatic "not editable" hint shown under a locked field. */
  description?: ReactNode
  /** Explanatory tooltip rendered as a sibling info glyph next to the label (never nested inside `<label>`). */
  hint?: string
  /** Accessible name of the hint trigger; defaults to `t('authorization.moreInfo')`. */
  hintLabel?: string
  /**
   * `stacked` (default) is the classic label-above-control form row.
   * `inline` is the settings-row layout — label + hint + description on the
   * left, the control pinned right — for a compact switch/select whose
   * explanation must stay readable next to it.
   */
  layout?: 'stacked' | 'inline'
  /**
   * Extra classes for the field row, e.g. the column span a caller's grid
   * needs. Set here rather than on a wrapper element so a hidden field
   * (`!visible` renders nothing) leaves no empty cell behind in that grid.
   */
  className?: string
  /**
   * Overrides the required marker when requiredness depends on live form
   * state the static field permission cannot express (e.g. a campaign's
   * classification fields, required only while standalone). Omit to follow
   * `permission.required`, the default for every other field.
   */
  required?: boolean
  /**
   * Renders the actual control, exactly as an inline `FormField` render prop
   * would (own `<FormControl>` placement included) — this keeps composition
   * identical to the existing forms for controls whose interactive element
   * sits inside a wrapper (e.g. `<Select><FormControl>…</FormControl>…</Select>`).
   */
  children: (args: MetaFieldRenderArgs<TFieldValues, TName>) => ReactNode
}

/**
 * Metadata-driven wrapper around the existing `FormField`. UI-only: no
 * authorization is derived here, only read from `useResourcePermissions()`.
 * - `!visible` → renders nothing.
 * - otherwise → renders the field with `disabled`/`readOnly` forwarded to the
 *   caller's control and `required` forwarded to the label.
 */
export function MetaField<
  TFieldValues extends FieldValues,
  TName extends FieldPath<TFieldValues>,
>({
  control,
  name,
  metaKey,
  label,
  description,
  hint,
  hintLabel,
  layout = 'stacked',
  className,
  required,
  children,
}: MetaFieldProps<TFieldValues, TName>) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const permission = fieldPermission(metaKey)

  if (!permission.visible) {
    return null
  }

  // A field that is not editable must be locked regardless of the raw
  // `disabled` flag: `readonly` fields are `editable: false` but not
  // necessarily `disabled: true` (see the spec's derivation rules).
  const disabled = permission.disabled || !permission.editable
  const readOnly = permission.readonly
  const requiredMark = required ?? permission.required

  const labelRow = hint ? (
    <div className="flex items-center gap-1.5">
      <FormLabel required={requiredMark}>{label}</FormLabel>
      <FieldHint text={hint} label={hintLabel ?? t('authorization.moreInfo')} />
    </div>
  ) : (
    <FormLabel required={requiredMark}>{label}</FormLabel>
  )

  const descriptionNode =
    description ??
    (disabled ? <FormDescription>{t('authorization.fieldNotEditable')}</FormDescription> : null)

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) =>
        layout === 'inline' ? (
          <FormItem className={className}>
            <div className="flex items-start justify-between gap-3">
              <div className="grid min-w-0 gap-1">
                {labelRow}
                {descriptionNode}
              </div>
              <div className="shrink-0">{children({ field, disabled, readOnly })}</div>
            </div>
            <FormMessage />
          </FormItem>
        ) : (
          <FormItem className={className}>
            {labelRow}
            {children({ field, disabled, readOnly })}
            {descriptionNode}
            <FormMessage />
          </FormItem>
        )
      }
    />
  )
}
