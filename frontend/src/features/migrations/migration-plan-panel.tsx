import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { SortableList, type SortableListItem } from '@/components/ui/sortable-list'
// Side effect: registers the `migrations` i18next namespace before this
// panel's own `t()` calls run.
import '@/features/migrations/i18n'
import { useMigrationPlan } from '@/features/migrations/use-migration-plan'
import type { MigrationPlanItem } from '@/features/migrations/types'

interface MigrationPlanPanelProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

interface PlanRow extends SortableListItem, MigrationPlanItem {}

/** Reorders the local list to match the ids emitted by the SortableList. */
function reorderBy(items: PlanRow[], orderedIds: string[]): PlanRow[] {
  return orderedIds
    .map((id) => items.find((item) => item.source === id))
    .filter((item): item is PlanRow => item !== undefined)
}

/**
 * Configure the mass-import plan (spec 0046): a drag & drop list of every
 * migration source with an "include" checkbox, saved once to the backend
 * singleton. Editing happens on a local copy re-seeded whenever the server plan
 * reference changes (React's adjust-state-on-prop-change recipe); Save persists
 * the order + flags via `PUT /migrations/plan`. The single-source select and
 * manual import (spec 0013) are untouched.
 */
export function MigrationPlanPanel({ open, onOpenChange }: MigrationPlanPanelProps) {
  const { t } = useTranslation('migrations')
  const { plan, isLoading, isError, save, isSaving, isSaved, saveError } = useMigrationPlan()

  // Local editable copy, re-synced whenever the server plan changes (initial
  // load and after a save returns the reconciled plan). Adjusted during render
  // rather than in an effect.
  const [rows, setRows] = useState<PlanRow[]>([])
  const [syncedSources, setSyncedSources] = useState<MigrationPlanItem[] | undefined>(undefined)
  if (plan && plan.sources !== syncedSources) {
    setSyncedSources(plan.sources)
    setRows(plan.sources.map((item) => ({ ...item, id: item.source })))
  }

  const label = (source: string, fallback: string) =>
    t(`sources.${source}`, { defaultValue: fallback })

  const toggleEnabled = (source: string) => {
    setRows((current) =>
      current.map((row) => (row.source === source ? { ...row, enabled: !row.enabled } : row)),
    )
  }

  const handleSave = () => {
    save(rows.map(({ source, enabled }) => ({ source, enabled })))
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" storageKey="sheet-width:migration-plan">
        <SheetHeader>
          <SheetTitle>{t('plan.title')}</SheetTitle>
          <SheetDescription>{t('plan.subtitle')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
          {isLoading ? (
            <div className="flex flex-col gap-1.5" aria-hidden="true">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : isError ? (
            <p className="text-sm text-destructive" role="alert">
              {t('plan.loadError')}
            </p>
          ) : (
            <SortableList
              items={rows}
              dragHandleLabel={t('plan.dragHandle')}
              onReorder={(orderedIds) => setRows((current) => reorderBy(current, orderedIds))}
              renderItem={(row) => (
                <label className="flex min-w-0 items-center gap-2">
                  <Checkbox
                    checked={row.enabled}
                    disabled={!row.available}
                    onCheckedChange={() => toggleEnabled(row.source)}
                    aria-label={t('plan.enabledLabel', { source: label(row.source, row.label) })}
                  />
                  <span className="truncate">{label(row.source, row.label)}</span>
                  {row.available ? null : (
                    <span className="shrink-0 text-xs text-muted-foreground">{t('plan.unavailable')}</span>
                  )}
                </label>
              )}
            />
          )}
        </div>

        <SheetFooter>
          {saveError ? (
            <p className="text-sm text-destructive" role="alert">
              {saveError}
            </p>
          ) : isSaved ? (
            <p className="text-sm text-muted-foreground" role="status">
              {t('plan.saved')}
            </p>
          ) : null}
          <Button type="button" onClick={handleSave} disabled={isLoading || isError || isSaving}>
            {isSaving ? t('plan.saving') : t('plan.save')}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
