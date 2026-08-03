import { useId, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { AreaTree } from '@/features/roles/permission-explorer/area-tree'
import {
  ModuleDetailPanel,
  type ModuleFieldPermissionsSlot,
} from '@/features/roles/permission-explorer/module-detail-panel'
import { filterPermissionCatalogue } from '@/features/roles/permission-explorer/permission-search'
import {
  toggleAreaPermissions,
  togglePermission,
  toggleResourcePermissions,
} from '@/features/roles/permission-explorer/permission-selection'
import type {
  PermissionCatalogueArea,
  PermissionCatalogueResource,
} from '@/features/roles/permission-catalogue-api'

interface PermissionExplorerProps {
  areas: PermissionCatalogueArea[]
  value: string[]
  disabled: boolean
  onChange: (permissions: string[]) => void
  /** `null` when the actor cannot manage field permissions at all (hides every module's fields section). */
  fieldPermissions: ModuleFieldPermissionsSlot | null
}

function firstResourceKey(areas: PermissionCatalogueArea[]): string | null {
  return areas[0]?.resources[0]?.resource ?? null
}

function findResource(areas: PermissionCatalogueArea[], resource: string | null): PermissionCatalogueResource | null {
  if (!resource) {
    return null
  }
  for (const area of areas) {
    const match = area.resources.find((entry) => entry.resource === resource)
    if (match) {
      return match
    }
  }
  return null
}

/**
 * Two-panel permission explorer (spec 0076): an Area > Module tree with
 * real-time search on the left, the selected module's actions and fields on
 * the right. The single source of layout/selection state; `AreaTree` and
 * `ModuleDetailPanel` stay presentational.
 */
export function PermissionExplorer({ areas, value, disabled, onChange, fieldPermissions }: PermissionExplorerProps) {
  const { t, i18n } = useTranslation()
  const searchInputId = useId()
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedResource, setSelectedResource] = useState<string | null>(() => firstResourceKey(areas))

  const filteredAreas = useMemo(
    () => filterPermissionCatalogue(areas, searchQuery, i18n),
    [areas, searchQuery, i18n],
  )
  const selected = findResource(areas, selectedResource)

  return (
    <div className="flex flex-col gap-3">
      <div className="relative">
        <label htmlFor={searchInputId} className="sr-only">
          {t('roles.permissionExplorer.searchLabel')}
        </label>
        <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <Input
          id={searchInputId}
          value={searchQuery}
          onChange={(event) => setSearchQuery(event.target.value)}
          placeholder={t('roles.permissionExplorer.searchPlaceholder')}
          className="pl-8"
        />
      </div>

      <div className="flex flex-col gap-3 min-[900px]:flex-row min-[900px]:items-start">
        <div className="min-w-0 min-[900px]:w-72 min-[900px]:shrink-0">
          <AreaTree
            areas={filteredAreas}
            searchActive={searchQuery.trim() !== ''}
            selectedResource={selectedResource}
            value={value}
            disabled={disabled}
            onSelectResource={setSelectedResource}
            onToggleResource={(resource, checked) => onChange(toggleResourcePermissions(resource, checked, value))}
            onToggleArea={(area, checked) => onChange(toggleAreaPermissions(area, checked, value))}
          />
        </div>

        <div className="min-w-0 flex-1">
          {selected && (
            <ModuleDetailPanel
              resource={selected}
              value={value}
              disabled={disabled}
              onTogglePermission={(name, checked) => onChange(togglePermission(value, name, checked))}
              fieldPermissions={fieldPermissions}
            />
          )}
        </div>
      </div>
    </div>
  )
}
