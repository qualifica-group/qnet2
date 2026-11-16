import { useState, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Columns3, Search } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { FILTERS_SHEET_BODY_CLASS, FiltersSheet, FiltersSheetFooter } from '@/components/ui/filters-sheet'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Input } from '@/components/ui/input'
import { Can } from '@/features/auth/can'
import {
  STATUS_COLUMN_KEYS,
  type ColumnStatusWorkflow,
} from '@/features/request-management/column-statuses-api'
import { REQUEST_STATISTICS_CONFIGURE_PERMISSION } from '@/features/request-management/types'
import { useRequestStatisticsColumnStatuses } from '@/features/request-management/use-request-statistics-column-statuses'

const SHEET_DEFAULT_WIDTH = 520
const FORM_ID = 'request-statistics-column-statuses-form'
const GROUP_CLASS = 'flex flex-col gap-2 rounded-lg border bg-card p-3 shadow-sm'
const STATUS_GRID_CLASS = 'grid grid-cols-1 gap-x-3 gap-y-1.5 sm:grid-cols-2'
const NOTE_CLASS = 'text-xs text-muted-foreground'

interface WorkflowGroupProps {
  workflow: ColumnStatusWorkflow
  chosen: number[]
  onChange: (ids: number[], checked: boolean) => void
}

/** One status set: its name, categories and the checkbox of each of its statuses. */
function WorkflowGroup({ workflow, chosen, onChange }: WorkflowGroupProps) {
  const { t } = useTranslation()
  const ids = workflow.statuses.map((status) => status.id)
  const chosenHere = ids.filter((id) => chosen.includes(id)).length
  const groupId = `column-statuses-workflow-${workflow.id ?? 'default'}`

  return (
    <section aria-labelledby={groupId} className={GROUP_CLASS}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="flex min-w-0 flex-col gap-0.5">
          <h3 id={groupId} className="flex items-center gap-1.5 text-sm font-semibold">
            <span className="truncate">{workflow.name}</span>
            {workflow.is_active ? null : <Badge variant="outline">{t('requestStatisticsColumns.inactive')}</Badge>}
          </h3>
          {workflow.categories.length > 0 ? (
            <p className={`${NOTE_CLASS} line-clamp-2`}>{workflow.categories.join(', ')}</p>
          ) : null}
        </div>
        <div className="flex shrink-0 items-center gap-1">
          <span className={NOTE_CLASS}>{t('requestStatisticsColumns.chosenCount', { count: chosenHere })}</span>
          <Button type="button" size="sm" variant="ghost" className="h-6 px-2 text-xs" onClick={() => onChange(ids, true)}>
            {t('requestStatisticsColumns.selectAll')}
          </Button>
          <Button type="button" size="sm" variant="ghost" className="h-6 px-2 text-xs" onClick={() => onChange(ids, false)}>
            {t('requestStatisticsColumns.selectNone')}
          </Button>
        </div>
      </div>

      {workflow.statuses.length === 0 ? (
        <p className={NOTE_CLASS}>{t('requestStatisticsColumns.noStatuses')}</p>
      ) : (
        <div className={STATUS_GRID_CLASS}>
          {workflow.statuses.map((status) => {
            const inputId = `column-status-${status.id}`
            return (
              <div key={status.id} className="flex min-w-0 items-center gap-2">
                <Checkbox
                  id={inputId}
                  checked={chosen.includes(status.id)}
                  onCheckedChange={(checked) => onChange([status.id], checked === true)}
                />
                <label htmlFor={inputId} className="flex min-w-0 items-center gap-1.5 text-xs">
                  {status.color ? (
                    <span aria-hidden="true" className="size-2 shrink-0 rounded-full" style={{ backgroundColor: status.color }} />
                  ) : null}
                  <span className="truncate">{status.name}</span>
                </label>
              </div>
            )
          })}
        </div>
      )}
    </section>
  )
}

/** The button and its sheet; mounted only for who may configure, so nothing is fetched for the others. */
function ColumnStatusesConfigurator() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const config = useRequestStatisticsColumnStatuses(open, () => setOpen(false))
  const columnLabel = (key: string) => t(`requestStatisticsColumns.columns.${key}`, { defaultValue: key })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      config.discard()
    }
    setOpen(next)
  }

  return (
    <>
      <Button
        type="button"
        size="icon-sm"
        variant="outline"
        onClick={() => setOpen(true)}
        aria-label={t('requestStatisticsColumns.open')}
        title={t('requestStatisticsColumns.open')}
      >
        <Columns3 aria-hidden="true" className="size-3.5" />
      </Button>

      <FiltersSheet
        open={open}
        onOpenChange={handleOpenChange}
        title={t('requestStatisticsColumns.title')}
        description={t('requestStatisticsColumns.description')}
        defaultWidth={SHEET_DEFAULT_WIDTH}
        storageKey="sheet-width:request-statistics-column-statuses"
      >
        <form
          id={FORM_ID}
          className={FILTERS_SHEET_BODY_CLASS}
          onSubmit={(event) => {
            event.preventDefault()
            config.save()
          }}
        >
          {config.isError ? (
            <p role="alert" className="text-sm text-destructive">
              {t('requestStatisticsColumns.loadError')}
            </p>
          ) : null}

          <FilterTabs
            aria-label={t('requestStatisticsColumns.columnsLabel')}
            value={config.activeColumn}
            onValueChange={config.setActiveColumn}
            options={STATUS_COLUMN_KEYS.map((key) => ({
              value: key,
              label: `${columnLabel(key)} (${config.countFor(key)})`,
            }))}
          />

          <p className={NOTE_CLASS}>
            {config.twinKey
              ? t('requestStatisticsColumns.twinNote', { column: columnLabel(config.activeColumn) })
              : t('requestStatisticsColumns.periodNote')}
          </p>

          <div className="relative">
            <Search aria-hidden="true" className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="search"
              value={config.search}
              onChange={(event: ChangeEvent<HTMLInputElement>) => config.setSearch(event.target.value)}
              placeholder={t('requestStatisticsColumns.searchPlaceholder')}
              aria-label={t('requestStatisticsColumns.searchPlaceholder')}
              className="h-8 bg-card pl-8 text-xs"
            />
          </div>

          {!config.isLoading && config.workflows.length === 0 && !config.isError ? (
            <p className={NOTE_CLASS}>{t('requestStatisticsColumns.noMatch')}</p>
          ) : null}

          {config.workflows.map((workflow) => (
            <WorkflowGroup
              key={workflow.id ?? 'default'}
              workflow={workflow}
              chosen={config.chosen}
              onChange={config.setStatuses}
            />
          ))}
        </form>
        <FiltersSheetFooter
          formId={FORM_ID}
          labels={{
            reset: t('requestStatisticsColumns.actions.reset'),
            cancel: t('requestStatisticsColumns.actions.cancel'),
            apply: t('requestStatisticsColumns.actions.save'),
          }}
          onReset={config.discard}
          onCancel={() => handleOpenChange(false)}
          disabled={config.isLoading || config.isSaving || !config.isDirty}
        />
      </FiltersSheet>
    </>
  )
}

/**
 * "Colonne e stati" (spec 0391 D-7): the page action, shown to who may
 * configure, and the sheet choosing which working statuses each
 * status-based column counts.
 */
export function RequestStatisticsColumnStatusesButton() {
  return (
    <Can permission={REQUEST_STATISTICS_CONFIGURE_PERMISSION}>
      <ColumnStatusesConfigurator />
    </Can>
  )
}
