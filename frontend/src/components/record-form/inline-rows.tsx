import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type FieldPath, type FieldValues } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { IconPickerControl } from '@/components/icon-picker-control'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { cn } from '@/lib/utils'

/**
 * Ready-made in-place rows for the fields every catalog record repeats (the
 * kit of the "no edit pages" plan, wave 3): text, long text, number, color,
 * icon, switch. Each row is a `RecordInlineField` whose editor is the field's
 * `MetaField` control and whose closed value is read from the form itself, so
 * the SAME row serves the detail (form = the saved record) and the create
 * draft. Must render inside the record's `<Form>` provider.
 */

interface InlineRowProps<TFieldValues extends FieldValues> {
  name: FieldPath<TFieldValues>
  label: string
  inline: InlineEdit
  /** Authorization metadata key, when it differs from `name`. */
  metaKey?: string
  /** `false` hides the pencil (no update permission, create-only field on a detail). */
  canEdit?: boolean
  /** Tooltip next to the editor's label. */
  hint?: string
}

function useRowValue<TFieldValues extends FieldValues>(name: FieldPath<TFieldValues>) {
  const { control } = useFormContext<TFieldValues>()
  return { control, value: useWatch({ control, name }) as unknown }
}

function textOf(value: unknown): string | null {
  if (value === null || value === undefined) {
    return null
  }
  const text = String(value)
  return text.trim() === '' ? null : text
}

interface TextInlineRowProps<TFieldValues extends FieldValues> extends InlineRowProps<TFieldValues> {
  /** A `Textarea` instead of an `Input`; the closed value keeps its line breaks. */
  multiline?: boolean
  /** Shown after the closed value (e.g. a unit). */
  suffix?: ReactNode
}

/** A free-text field (name, code, description...): empty strings are sent as typed, `null` stays `null`. */
export function TextInlineRow<TFieldValues extends FieldValues>({
  name,
  label,
  inline,
  metaKey = name,
  canEdit = true,
  hint,
  multiline = false,
  suffix,
}: TextInlineRowProps<TFieldValues>) {
  const { control, value } = useRowValue<TFieldValues>(name)
  const text = textOf(value)

  return (
    <RecordInlineField
      field={name}
      metaKey={metaKey}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={metaKey} label={label} hint={hint}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              {multiline ? (
                <Textarea
                  className="min-h-20"
                  disabled={disabled}
                  readOnly={readOnly}
                  name={field.name}
                  value={(field.value as string | null) ?? ''}
                  onBlur={field.onBlur}
                  onChange={(event) => field.onChange(event.target.value === '' ? null : event.target.value)}
                />
              ) : (
                <Input
                  autoComplete="off"
                  disabled={disabled}
                  readOnly={readOnly}
                  name={field.name}
                  value={(field.value as string | null) ?? ''}
                  onBlur={field.onBlur}
                  onChange={(event) => field.onChange(event.target.value)}
                />
              )}
            </FormControl>
          )}
        </MetaField>
      }
    >
      {text !== null ? (
        <span className="break-words whitespace-pre-wrap">
          {text}
          {suffix ? <span className="text-muted-foreground"> {suffix}</span> : null}
        </span>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

interface NumberInlineRowProps<TFieldValues extends FieldValues> extends InlineRowProps<TFieldValues> {
  min?: number
  max?: number
  step?: number
  suffix?: ReactNode
}

/** A numeric field: an empty input becomes `null`, otherwise a `number`. */
export function NumberInlineRow<TFieldValues extends FieldValues>({
  name,
  label,
  inline,
  metaKey = name,
  canEdit = true,
  hint,
  min,
  max,
  step,
  suffix,
}: NumberInlineRowProps<TFieldValues>) {
  const { control, value } = useRowValue<TFieldValues>(name)

  return (
    <RecordInlineField
      field={name}
      metaKey={metaKey}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={metaKey} label={label} hint={hint}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                inputMode="decimal"
                min={min}
                max={max}
                step={step}
                disabled={disabled}
                readOnly={readOnly}
                name={field.name}
                value={field.value === null || field.value === undefined ? '' : String(field.value)}
                onBlur={field.onBlur}
                onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
              />
            </FormControl>
          )}
        </MetaField>
      }
    >
      {value === null || value === undefined || value === '' ? (
        <DetailEmpty />
      ) : (
        <span className="tabular-nums">
          {String(value)}
          {suffix ? <span className="text-muted-foreground"> {suffix}</span> : null}
        </span>
      )}
    </RecordInlineField>
  )
}

/** A `BADGE_COLOR_TOKENS` color: swatch + localized token name when closed, the shared token picker when open. */
export function ColorInlineRow<TFieldValues extends FieldValues>({
  name,
  label,
  inline,
  metaKey = name,
  canEdit = true,
  hint,
}: InlineRowProps<TFieldValues>) {
  const { t } = useTranslation()
  const { control, value } = useRowValue<TFieldValues>(name)
  const token = textOf(value)

  return (
    <RecordInlineField
      field={name}
      metaKey={metaKey}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={metaKey} label={label} hint={hint}>
          {({ field, disabled }) => (
            <FormControl>
              <ColorTokenPicker value={field.value} onChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>
      }
    >
      {token !== null ? (
        <span className="flex items-center gap-2">
          <span
            className={cn('size-3.5 shrink-0 rounded-full border', swatchClassFor(token) ?? 'bg-transparent')}
            aria-hidden="true"
          />
          {t(`customFields.colors.${token}`)}
        </span>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

/** A curated lucide icon: glyph + canonical name when closed, the shared icon picker when open. */
export function IconInlineRow<TFieldValues extends FieldValues>({
  name,
  label,
  inline,
  metaKey = name,
  canEdit = true,
  hint,
}: InlineRowProps<TFieldValues>) {
  const { control, value } = useRowValue<TFieldValues>(name)
  const icon = textOf(value)

  return (
    <RecordInlineField
      field={name}
      metaKey={metaKey}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={metaKey} label={label} hint={hint}>
          {({ field, disabled, readOnly }) => (
            <IconPickerControl
              value={(field.value as string | null) ?? ''}
              onChange={field.onChange}
              disabled={disabled}
              readOnly={readOnly}
            />
          )}
        </MetaField>
      }
    >
      {icon !== null ? (
        <span className="flex items-center gap-2">
          <DynamicIcon name={icon} className="size-3.5 text-muted-foreground" />
          {icon}
        </span>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

/** A boolean flag (active, default...): "Si'"/"No" when closed, a switch when open. */
export function SwitchInlineRow<TFieldValues extends FieldValues>({
  name,
  label,
  inline,
  metaKey = name,
  canEdit = true,
  hint,
}: InlineRowProps<TFieldValues>) {
  const { t } = useTranslation()
  const { control, value } = useRowValue<TFieldValues>(name)

  return (
    <RecordInlineField
      field={name}
      metaKey={metaKey}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={metaKey} label={label} hint={hint} layout="inline">
          {({ field, disabled }) => (
            <FormControl>
              <Switch checked={Boolean(field.value)} onCheckedChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>
      }
    >
      <span>{value ? t('common.yes') : t('common.no')}</span>
    </RecordInlineField>
  )
}
