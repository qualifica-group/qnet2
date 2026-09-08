import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import type { GlobalFieldSource } from '@/features/imports/wizard/global-field-source'

interface ImportCampaignSourceProps {
  /** Field label, already resolved through the default i18n namespace. */
  label: string
  /** Label of the mappable field the "from file" option needs mapped. */
  mappedFieldLabel: string
  value: GlobalFieldSource
  onChange: (next: GlobalFieldSource) => void
  /** True when "from file" is chosen but no column carries that field yet. */
  columnMissing: boolean
}

/**
 * The two mutually exclusive ways to feed a `required_unless_mapped` global
 * field (spec 0108 AC-030): one value for the whole run, or one per row read
 * from a mapped file column. Native radios — there is no `radio-group.tsx` in
 * `components/ui/`, and a feature slice does not introduce a shared primitive
 * (same reasoning as `custom-fields/enum-radio-group.tsx`).
 */
export function ImportCampaignSource({
  label,
  mappedFieldLabel,
  value,
  onChange,
  columnMissing,
}: ImportCampaignSourceProps) {
  const { t } = useTranslation('importWizard')
  const groupName = useId()
  const hintId = useId()

  const options: Array<{ value: GlobalFieldSource; title: string; description: string }> = [
    {
      value: 'run',
      title: t('config.source.run.title', { field: label }),
      description: t('config.source.run.description', { field: label }),
    },
    {
      value: 'file',
      title: t('config.source.file.title', { field: mappedFieldLabel }),
      description: t('config.source.file.description', { field: mappedFieldLabel }),
    },
  ]

  return (
    <div className="flex flex-col gap-1.5">
      <div
        role="radiogroup"
        aria-label={t('config.source.legend', { field: label })}
        aria-describedby={columnMissing ? hintId : undefined}
        className="grid gap-2 sm:grid-cols-2"
      >
        {options.map((option) => (
          <label
            key={option.value}
            className={cn(
              'flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-xs transition-colors',
              value === option.value ? 'border-primary bg-accent/40' : 'hover:bg-muted/40',
            )}
          >
            <input
              type="radio"
              name={groupName}
              value={option.value}
              checked={value === option.value}
              onChange={() => onChange(option.value)}
              className="mt-0.5 size-3.5 accent-primary"
            />
            <span className="flex min-w-0 flex-col gap-0.5">
              <span className="font-medium">{option.title}</span>
              <span className="text-muted-foreground">{option.description}</span>
            </span>
          </label>
        ))}
      </div>
      {columnMissing ? (
        <p id={hintId} role="alert" className="text-xs text-destructive">
          {t('config.source.columnMissing', { field: mappedFieldLabel })}
        </p>
      ) : null}
    </div>
  )
}
