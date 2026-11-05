import { Controller, type Control, type FieldPath } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { MoveRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FieldHint } from '@/components/field-hint'
import type { ImportMappingFormValues } from '@/features/imports/wizard/import-mapping-schema'
import type { AttributeOption } from '@/features/imports/wizard/mapping-attribute-options'
import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { DetectedColumn, ImportFieldDescriptor } from '@/features/imports/wizard/types'

interface MappingColumnsTableProps {
  columns: DetectedColumn[]
  fields: ImportFieldDescriptor[]
  /** `attribute.<code>` targets offered next to the domain fields (spec 0348); `[]` for leads. */
  attributeOptions: AttributeOption[]
  control: Control<ImportMappingFormValues>
  /** Current target per column index. */
  mappingValues: Record<string, string>
  conflictFieldIds: ReadonlySet<string>
  /** Domain key (`run.resource`) selecting the `domains.<resource>.*` wording overrides. */
  domain?: string
}

/** One target select per detected file column (extracted from the mapping step, which only orchestrates). */
export function MappingColumnsTable({
  columns,
  fields,
  attributeOptions,
  control,
  mappingValues,
  conflictFieldIds,
  domain,
}: MappingColumnsTableProps) {
  const { t } = useTranslation('importWizard')
  const targetHint = domain
    ? t(`domains.${domain}.targetHint`, { defaultValue: t('mapping.hints.target') })
    : t('mapping.hints.target')
  // Field labels come from the backend as default-namespace i18n keys
  // (`imports.leads.fields.*`), so they resolve through the default translator.
  const { t: tLabel } = useTranslation()

  return (
    <div className="overflow-hidden rounded-lg border">
      <div className="hidden gap-2 border-b bg-muted/40 px-3 py-2 sm:grid sm:grid-cols-[minmax(0,1fr)_1.25rem_16rem]">
        <span className="text-xs font-medium text-muted-foreground">{t('mapping.columnHeader')}</span>
        <span aria-hidden="true" />
        <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
          {t('mapping.targetHeader')}
          <FieldHint text={targetHint} label={t('mapping.hints.targetLabel')} />
        </span>
      </div>
      <div className="divide-y">
        {columns.map((column) => {
          const fieldName = `mapping.${column.index}` as FieldPath<ImportMappingFormValues>
          const currentTarget = mappingValues[String(column.index)] ?? IGNORE_TARGET
          const isConflict = conflictFieldIds.has(currentTarget)
          const isMapped = currentTarget !== IGNORE_TARGET

          return (
            <div
              key={`${column.index}-${column.name}`}
              className="flex flex-col gap-1.5 px-3 py-2.5 transition-colors hover:bg-muted/40 sm:grid sm:grid-cols-[minmax(0,1fr)_1.25rem_16rem] sm:items-center sm:gap-2"
            >
              <div className="flex min-w-0 flex-wrap items-center gap-2">
                <span className="truncate text-sm font-medium">{column.name}</span>
                {column.duplicate ? <Badge variant="outline">{t('mapping.badges.duplicateColumn')}</Badge> : null}
                {isConflict ? <Badge variant="destructive">{t('mapping.badges.conflict')}</Badge> : null}
              </div>
              <MoveRight
                aria-hidden="true"
                className={cn(
                  'hidden size-4 transition-colors sm:block',
                  isMapped ? 'text-primary' : 'text-muted-foreground/40',
                )}
              />
              <Controller
                control={control}
                name={fieldName}
                render={({ field }) => (
                  <Select value={(field.value as string) || IGNORE_TARGET} onValueChange={field.onChange}>
                    <SelectTrigger
                      className={cn('w-full', !isMapped && 'text-muted-foreground')}
                      aria-label={t('mapping.targetHeader')}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={IGNORE_TARGET}>{t('mapping.ignore')}</SelectItem>
                      <SelectItem value={EXTRA_TARGET}>{t('mapping.extra')}</SelectItem>
                      {fields.map((mappableField) => (
                        <SelectItem key={mappableField.id} value={mappableField.id}>
                          {mappableField.required ? `${tLabel(mappableField.label)} *` : tLabel(mappableField.label)}
                        </SelectItem>
                      ))}
                      {attributeOptions.map((option) => (
                        <SelectItem key={option.target} value={option.target}>
                          {t('mapping.attributeTarget', { name: option.name })}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
            </div>
          )
        })}
      </div>
    </div>
  )
}
