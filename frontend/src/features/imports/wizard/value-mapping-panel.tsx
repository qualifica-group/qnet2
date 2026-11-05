import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { ListChecks } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type {
  ValueMappingEntry,
  ValueMappingField,
  WorkflowStatusGroup,
} from '@/features/imports/wizard/types'
import type { ValueMappingController } from '@/features/imports/wizard/use-value-mapping'
import { createNameError, effectiveEntry, WORKFLOW_STATUS_GROUPS } from '@/features/imports/wizard/value-mapping-state'
import { StepAlert, StepSectionHeader } from '@/features/imports/wizard/wizard-ui'

/** Select value of the "create a new status" choice (never a numeric id). */
const CREATE_OPTION = '__create__'
const NEW_STATUS_NAME_MAX_LENGTH = 100
const DEFAULT_NEW_STATUS_GROUP: WorkflowStatusGroup = 'open'

function selectValueOf(entry: ValueMappingEntry | undefined): string {
  if (!entry) return ''
  return 'id' in entry ? String(entry.id) : CREATE_OPTION
}

interface ValueRowProps {
  field: ValueMappingField
  value: ValueMappingField['values'][number]
  entry: ValueMappingEntry | undefined
  onChange: (entry: ValueMappingEntry) => void
}

function ValueRow({ field, value, entry, onChange }: ValueRowProps) {
  const { t } = useTranslation('importWizard')
  const label = value.value === '' ? t('valueMapping.emptyValue') : value.value
  const nameError = createNameError(field, entry)
  const unmatched = !entry || nameError !== null
  const errorId = useId()
  const create = entry && 'create' in entry ? entry.create : null

  function handleSelect(next: string) {
    if (next === CREATE_OPTION) {
      onChange({ create: { name: create?.name ?? value.value, group: create?.group ?? DEFAULT_NEW_STATUS_GROUP } })
      return
    }
    onChange({ id: Number(next) })
  }

  return (
    <li
      className={cn(
        'flex flex-col gap-1.5 px-3 py-2 sm:grid sm:grid-cols-[minmax(0,1fr)_3rem_16rem] sm:items-start sm:gap-2',
        unmatched && 'border-l-2 border-destructive bg-destructive/5',
      )}
    >
      <span className={cn('truncate text-sm', value.value === '' && 'italic text-muted-foreground')} title={label}>
        {label}
      </span>
      <Badge variant="outline" className="w-fit tabular-nums" aria-label={t('valueMapping.count', { count: value.count })}>
        {value.count}
      </Badge>
      <div className="flex flex-col gap-1.5">
        <Select value={selectValueOf(entry)} onValueChange={handleSelect}>
          <SelectTrigger
            size="sm"
            className="w-full"
            aria-label={t('valueMapping.selectLabel', { value: label })}
            aria-invalid={unmatched}
          >
            <SelectValue placeholder={t('valueMapping.placeholder')} />
          </SelectTrigger>
          <SelectContent>
            {field.options.map((option) => (
              <SelectItem key={option.id} value={String(option.id)}>
                {option.name}
              </SelectItem>
            ))}
            {field.allow_create ? <SelectItem value={CREATE_OPTION}>{t('valueMapping.createNew')}</SelectItem> : null}
          </SelectContent>
        </Select>
        {create ? (
          <div className="flex flex-col gap-1.5">
            <Input
              className="h-8 text-xs"
              maxLength={NEW_STATUS_NAME_MAX_LENGTH}
              value={create.name}
              aria-label={t('valueMapping.newName', { value: label })}
              aria-invalid={nameError !== null}
              aria-describedby={nameError ? errorId : undefined}
              onChange={(event) => onChange({ create: { ...create, name: event.target.value } })}
            />
            {nameError ? (
              <span id={errorId} role="alert" className="text-xs text-destructive">
                {t(`valueMapping.nameErrors.${nameError}`)}
              </span>
            ) : null}
            <Select
              value={create.group}
              onValueChange={(group) => onChange({ create: { ...create, group: group as WorkflowStatusGroup } })}
            >
              <SelectTrigger size="sm" className="w-full" aria-label={t('valueMapping.newGroup', { value: label })}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {WORKFLOW_STATUS_GROUPS.map((group) => (
                  <SelectItem key={group} value={group}>
                    {t(`valueMapping.groups.${group}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        ) : null}
      </div>
    </li>
  )
}

interface ValueMappingPanelProps {
  controller: ValueMappingController
}

/**
 * "Valori" panel (spec 0348): for every distinct file value of a mapped
 * value-mapping field (status, source) the operator picks the existing record
 * it stands for — or, for statuses, asks for a new one. The step stays
 * blocked until each value has a match, so nothing is guessed at import time.
 */
export function ValueMappingPanel({ controller }: ValueMappingPanelProps) {
  const { t } = useTranslation('importWizard')
  const { t: tLabel } = useTranslation()

  return (
    <section aria-label={t('valueMapping.title')} className="flex flex-col gap-3">
      <StepSectionHeader icon={ListChecks} title={t('valueMapping.title')} description={t('valueMapping.subtitle')} />

      {controller.awaitingCategory ? (
        <StepAlert tone="warning" role="status">
          {t('valueMapping.needsProductLine')}
        </StepAlert>
      ) : null}

      {controller.isLoading ? (
        <p role="status" className="text-xs text-muted-foreground">
          {t('valueMapping.loading')}
        </p>
      ) : null}

      {controller.isError ? (
        <StepAlert>
          <span>{t('valueMapping.loadError')}</span>
          <Button type="button" size="sm" variant="outline" className="ml-2" onClick={() => void controller.refetch()}>
            {t('config.select.retry')}
          </Button>
        </StepAlert>
      ) : null}

      {controller.fields.map((field) => (
        <div key={field.field} className="overflow-hidden rounded-lg border">
          <h4 className="border-b bg-muted/40 px-3 py-1.5 text-xs font-medium text-muted-foreground">
            {tLabel(`imports.request-management.fields.${field.field}`, { defaultValue: field.field })}
          </h4>
          <ul className="divide-y">
            {field.values.map((value) => (
              <ValueRow
                key={value.value}
                field={field}
                value={value}
                entry={effectiveEntry(field, value, controller.manual, controller.template)}
                onChange={(entry) => controller.setEntry(field.field, value.value, entry)}
              />
            ))}
          </ul>
        </div>
      ))}

      {controller.unmatched > 0 ? (
        <StepAlert tone="warning" role="status">
          {t('valueMapping.unmatched', { count: controller.unmatched })}
        </StepAlert>
      ) : null}
    </section>
  )
}
