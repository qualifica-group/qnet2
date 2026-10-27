import { createElement, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { resolveIcon } from '@/features/navigation/icon-map'
import { useNavigationCatalogue } from '@/features/workspaces/use-workspaces'
import { groupEntries, toggleKeys, type EntryGroup } from '@/features/workspaces/workspace-entries'
import { cn } from '@/lib/utils'

interface WorkspaceEntriesPickerProps {
  value: string[]
  onChange: (keys: string[]) => void
}

/** Searchable checkbox selector of the menu entries an app shows, grouped by section. */
export function WorkspaceEntriesPicker({ value, onChange }: WorkspaceEntriesPickerProps) {
  const { t } = useTranslation('workspaces')
  const { t: translateMenu } = useTranslation()
  const catalogue = useNavigationCatalogue()
  const [query, setQuery] = useState('')

  const groups = useMemo(
    () => groupEntries(catalogue.data ?? [], translateMenu, query),
    [catalogue.data, translateMenu, query],
  )

  if (catalogue.isError) {
    return (
      <p role="alert" className="text-sm text-destructive">
        {t('form.catalogueLoadError')}
      </p>
    )
  }
  if (!catalogue.data) {
    return (
      <div className="flex flex-col gap-2" aria-busy="true">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-20 w-full" />
      </div>
    )
  }

  const visibleKeys = groups.flatMap((group) => group.entries.map((entry) => entry.key))

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-0 flex-1">
          <Search
            className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={t('form.entriesSearch')}
            aria-label={t('form.entriesSearch')}
            className="h-8 pl-8 md:text-xs"
          />
        </div>
        <Button
          type="button"
          size="xs"
          variant="secondary"
          disabled={visibleKeys.length === 0}
          onClick={() => onChange(toggleKeys(value, visibleKeys, true))}
        >
          {t('form.entriesSelectAll')}
        </Button>
        <Button
          type="button"
          size="xs"
          variant="secondary"
          disabled={value.length === 0}
          onClick={() => onChange(toggleKeys(value, visibleKeys, false))}
        >
          {t('form.entriesClear')}
        </Button>
      </div>

      {groups.length === 0 ? (
        <p className="py-4 text-center text-xs text-muted-foreground">{t('form.entriesNoMatch')}</p>
      ) : (
        <div className="flex flex-col divide-y divide-border">
          {groups.map((group) => (
            <EntryGroupBlock
              key={group.title}
              group={group}
              value={value}
              onChange={onChange}
              translate={translateMenu}
            />
          ))}
        </div>
      )}
    </div>
  )
}

interface EntryGroupBlockProps {
  group: EntryGroup
  value: string[]
  onChange: (keys: string[]) => void
  translate: (key: string) => string
}

function EntryGroupBlock({ group, value, onChange, translate }: EntryGroupBlockProps) {
  const { t } = useTranslation('workspaces')
  const keys = group.entries.map((entry) => entry.key)
  const selectedCount = keys.filter((key) => value.includes(key)).length
  const groupState = selectedCount === 0 ? false : selectedCount === keys.length ? true : 'indeterminate'
  const groupId = `workspace-group-${keys[0]}`
  const title = group.title || t('form.entriesTopLevel')

  return (
    <div role="group" aria-labelledby={`${groupId}-label`} className="grid gap-1 py-2.5 first:pt-0 last:pb-0">
      <div className="flex items-center gap-2 px-2">
        <Checkbox
          id={groupId}
          checked={groupState}
          onCheckedChange={(checked) => onChange(toggleKeys(value, keys, checked === true))}
        />
        <Label
          id={`${groupId}-label`}
          htmlFor={groupId}
          className="min-w-0 flex-1 truncate text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
          {title}
        </Label>
        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
          {selectedCount}/{keys.length}
        </span>
      </div>
      <div className="grid gap-0.5 sm:grid-cols-2">
        {group.entries.map((entry) => {
          const id = `workspace-entry-${entry.key}`
          const checked = value.includes(entry.key)
          return (
            <div
              key={entry.key}
              className={cn(
                'flex items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-muted/40',
                checked && 'bg-primary/5',
              )}
            >
              <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(next) => onChange(toggleKeys(value, [entry.key], next === true))}
              />
              {createElement(resolveIcon(entry.icon), {
                className: cn('size-3.5 shrink-0', checked ? 'text-primary' : 'text-muted-foreground'),
                'aria-hidden': true,
              })}
              <Label htmlFor={id} className="min-w-0 flex-1 cursor-pointer truncate text-sm font-normal">
                {translate(entry.label)}
              </Label>
            </div>
          )
        })}
      </div>
    </div>
  )
}
