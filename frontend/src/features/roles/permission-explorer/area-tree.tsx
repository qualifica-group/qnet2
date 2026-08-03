import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { AriaCheckbox } from '@/features/roles/permission-explorer/checkbox-controls'
import {
  areaSelectionCount,
  areaSelectionState,
  resourceSelectionCount,
  resourceSelectionState,
} from '@/features/roles/permission-explorer/permission-selection'
import type {
  PermissionCatalogueArea,
  PermissionCatalogueResource,
} from '@/features/roles/permission-catalogue-api'

interface AreaTreeProps {
  /** Already filtered by the caller's search query (spec 0076 AC-012). */
  areas: PermissionCatalogueArea[]
  /** Forces every area open regardless of manual collapse state (a live search). */
  searchActive: boolean
  selectedResource: string | null
  value: string[]
  disabled: boolean
  onSelectResource: (resource: string) => void
  onToggleResource: (resource: PermissionCatalogueResource, checked: boolean) => void
  onToggleArea: (area: PermissionCatalogueArea, checked: boolean) => void
}

/** Left panel of the permission explorer (spec 0076): the Area > Module tree. */
export function AreaTree({
  areas,
  searchActive,
  selectedResource,
  value,
  disabled,
  onSelectResource,
  onToggleResource,
  onToggleArea,
}: AreaTreeProps) {
  const { t } = useTranslation()
  const [manuallyOpen, setManuallyOpen] = useState<Set<string>>(() => new Set([areas[0]?.key].filter((key): key is string => !!key)))

  if (areas.length === 0) {
    return <p className="p-3 text-sm text-muted-foreground">{t('roles.permissionExplorer.searchEmpty')}</p>
  }

  const toggleManualOpen = (key: string, open: boolean) => {
    setManuallyOpen((current) => {
      const next = new Set(current)
      if (open) {
        next.add(key)
      } else {
        next.delete(key)
      }
      return next
    })
  }

  return (
    <div className="flex flex-col gap-2">
      {areas.map((area) => {
        const counts = areaSelectionCount(area, value)
        const isOpen = searchActive || manuallyOpen.has(area.key)
        const areaLabel = t(area.label_key)

        return (
          <Collapsible key={area.key} open={isOpen} onOpenChange={(open) => toggleManualOpen(area.key, open)}>
            <div className="rounded-lg border bg-card">
              <div className="flex items-center gap-2 px-2.5 py-1.5">
                <AriaCheckbox
                  checked={areaSelectionState(area, value)}
                  disabled={disabled}
                  label={`${areaLabel} — ${t('roles.permissionExplorer.selectAllArea')}`}
                  onChange={(checked) => onToggleArea(area, checked)}
                />
                <CollapsibleTrigger className="flex flex-1 items-center gap-1.5 text-left text-xs font-medium outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50 [&[data-state=open]>svg]:rotate-180">
                  <ChevronDown className="size-3.5 shrink-0 text-muted-foreground transition-transform" aria-hidden="true" />
                  <span className="truncate">{areaLabel}</span>{' '}
                  <Badge variant="secondary" className="ml-auto text-[10px]">
                    {t('roles.permissionExplorer.selectionCount', { selected: counts.selected, total: counts.total })}
                  </Badge>
                </CollapsibleTrigger>
              </div>
              <CollapsibleContent>
                <div className="flex flex-col gap-0.5 border-t px-1.5 py-1.5">
                  {area.resources.map((resource) => (
                    <ModuleRow
                      key={resource.resource}
                      resource={resource}
                      value={value}
                      disabled={disabled}
                      selected={selectedResource === resource.resource}
                      onSelect={() => onSelectResource(resource.resource)}
                      onToggle={onToggleResource}
                    />
                  ))}
                </div>
              </CollapsibleContent>
            </div>
          </Collapsible>
        )
      })}
    </div>
  )
}

interface ModuleRowProps {
  resource: PermissionCatalogueResource
  value: string[]
  disabled: boolean
  selected: boolean
  onSelect: () => void
  onToggle: (resource: PermissionCatalogueResource, checked: boolean) => void
}

/** One module row: tri-state checkbox, name, and its `selected/total` badge. */
function ModuleRow({ resource, value, disabled, selected, onSelect, onToggle }: ModuleRowProps) {
  const { t } = useTranslation()
  const label = t(resource.label_key)
  const counts = resourceSelectionCount(resource, value)

  return (
    <div className={cn('flex items-center gap-2 rounded-md px-1.5 py-1', selected && 'bg-accent')}>
      <AriaCheckbox
        checked={resourceSelectionState(resource, value)}
        disabled={disabled}
        label={`${label} — ${t('roles.form.selectAll')}`}
        onChange={(checked) => onToggle(resource, checked)}
      />
      <button
        type="button"
        aria-current={selected ? 'true' : undefined}
        onClick={onSelect}
        className="flex flex-1 items-center justify-between gap-2 rounded text-left text-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50"
      >
        <span className="truncate">{label}</span>{' '}
        <Badge variant="secondary" className="text-[10px]">
          {t('roles.permissionExplorer.selectionCount', { selected: counts.selected, total: counts.total })}
        </Badge>
      </button>
    </div>
  )
}
