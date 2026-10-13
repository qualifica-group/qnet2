import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { usePermissionCatalogue } from '@/features/roles/use-permission-catalogue'
import { abilityLabel } from '@/features/roles/permission-labels'
import type { PermissionCatalogueArea } from '@/features/roles/permission-catalogue-api'

/** The closed value of the detail's "Membri" row: the assigned users' names, `#id` while a label loads. */
export function RoleMembers({ ids }: { ids: number[] }) {
  const labels = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids })

  if (ids.length === 0) {
    return <DetailEmpty />
  }

  return (
    <div className="flex flex-wrap gap-1">
      {ids.map((id) => (
        <Badge key={id} variant="secondary">
          {labels.get(id)?.label ?? `#${id}`}
        </Badge>
      ))}
    </div>
  )
}

/**
 * The closed value of the "Permessi" row: the abilities this role grants,
 * grouped by the same Area > Module taxonomy as the permission explorer
 * (spec 0076).
 */
export function RolePermissionsOverview({ granted }: { granted: string[] }) {
  const { t, i18n } = useTranslation()
  const catalogueQuery = usePermissionCatalogue()

  if (granted.length === 0) {
    return <DetailEmpty />
  }

  if (catalogueQuery.isPending) {
    return (
      <div className="flex flex-col gap-2" aria-hidden="true">
        <Skeleton className="h-6 w-full" />
        <Skeleton className="h-6 w-full" />
      </div>
    )
  }

  if (catalogueQuery.isError) {
    return (
      <p className="text-sm text-destructive" role="alert">
        {t('authorization.loadError')}
      </p>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      {groupGrantedByArea(catalogueQuery.data.areas, granted).map(({ area, resources }) => (
        <div key={area.key} className="flex flex-col gap-2">
          <span className="text-xs font-semibold text-muted-foreground">{t(area.label_key)}</span>
          {resources.map(({ resource, granted: abilities }) => (
            <div key={resource.resource} className="flex flex-col gap-1.5">
              <span className="text-xs font-medium text-muted-foreground">{t(resource.label_key)}</span>
              <div className="flex flex-wrap gap-1">
                {abilities.map((permission) => (
                  <Badge key={permission.name} variant="secondary">
                    {abilityLabel(permission.name, i18n)}
                  </Badge>
                ))}
              </div>
            </div>
          ))}
        </div>
      ))}
    </div>
  )
}

interface GrantedResourceGroup {
  resource: PermissionCatalogueArea['resources'][number]
  granted: PermissionCatalogueArea['resources'][number]['permissions']
}

interface GrantedAreaGroup {
  area: PermissionCatalogueArea
  resources: GrantedResourceGroup[]
}

/**
 * Narrows the full Area > Module catalogue down to only the modules (and,
 * within each, only the abilities) actually granted by this role.
 */
function groupGrantedByArea(areas: PermissionCatalogueArea[], grantedNames: string[]): GrantedAreaGroup[] {
  const granted = new Set(grantedNames)

  return areas
    .map((area) => ({
      area,
      resources: area.resources
        .map((resource) => ({
          resource,
          granted: resource.permissions.filter((permission) => granted.has(permission.name)),
        }))
        .filter((entry) => entry.granted.length > 0),
    }))
    .filter((entry) => entry.resources.length > 0)
}
