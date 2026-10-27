import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useNavigationCatalogue } from '@/features/workspaces/use-workspaces'
import type { NavigationCatalogueEntry } from '@/features/workspaces/types'

interface WorkspaceEntriesPickerProps {
  value: string[]
  onChange: (keys: string[]) => void
}

interface EntryGroup {
  title: string
  entries: NavigationCatalogueEntry[]
}

/** Entries grouped by their ancestors, in catalogue (= menu) order. */
function groupEntries(
  catalogue: NavigationCatalogueEntry[],
  translate: (key: string) => string,
): EntryGroup[] {
  const groups = new Map<string, EntryGroup>()
  for (const entry of catalogue) {
    const title = entry.path.map(translate).join(' › ')
    const group = groups.get(title) ?? { title, entries: [] }
    group.entries.push(entry)
    groups.set(title, group)
  }
  return [...groups.values()]
}

/** Checkbox selector of the menu entries an app shows, grouped by section. */
export function WorkspaceEntriesPicker({ value, onChange }: WorkspaceEntriesPickerProps) {
  const { t } = useTranslation('workspaces')
  const { t: translateMenu } = useTranslation()
  const catalogue = useNavigationCatalogue()

  const groups = useMemo(
    () => groupEntries(catalogue.data ?? [], translateMenu),
    [catalogue.data, translateMenu],
  )

  if (catalogue.isError) {
    return (
      <p role="alert" className="text-sm text-destructive">
        {t('form.catalogueLoadError')}
      </p>
    )
  }
  if (!catalogue.data) {
    return <Skeleton className="h-24 w-full" aria-busy="true" />
  }

  const toggle = (key: string, checked: boolean) =>
    onChange(checked ? [...value, key] : value.filter((item) => item !== key))

  return (
    <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3">
      {groups.map((group) => (
        <fieldset key={group.title} className="grid gap-1.5">
          {group.title ? (
            <legend className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {group.title}
            </legend>
          ) : null}
          <div className="grid gap-1.5 sm:grid-cols-2">
            {group.entries.map((entry) => {
              const id = `workspace-entry-${entry.key}`
              return (
                <div key={entry.key} className="flex items-center gap-2">
                  <Checkbox
                    id={id}
                    checked={value.includes(entry.key)}
                    onCheckedChange={(checked) => toggle(entry.key, checked === true)}
                  />
                  <Label htmlFor={id} className="min-w-0 truncate text-sm font-normal">
                    {translateMenu(entry.label)}
                  </Label>
                </div>
              )
            })}
          </div>
        </fieldset>
      ))}
    </div>
  )
}
