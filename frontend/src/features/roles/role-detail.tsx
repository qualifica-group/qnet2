import { useTranslation } from 'react-i18next'
import { KeyRound, ShieldCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailEmpty, DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRole } from '@/features/roles/api'
import { usePermissionCatalogue } from '@/features/roles/use-permission-catalogue'
import { abilityLabel } from '@/features/roles/permission-labels'
import type { PermissionCatalogueArea } from '@/features/roles/permission-catalogue-api'

interface RoleDetailProps {
  roleId: number
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single role, fetched fresh from the (re-authorized)
 * detail endpoint, rendered as an enterprise-CRM record (Opportunita'
 * reference kit): the identity/permissions card on the left, the Attivita'
 * tab on the right when authorized, a metadata footer. Permissions are
 * grouped by the same Area > Module taxonomy as the form's permission
 * explorer (spec 0076).
 */
export function RoleDetailView({ roleId, onEdit }: RoleDetailProps) {
  const { t, i18n } = useTranslation()
  const {
    data: role,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['roles', 'detail', roleId], () => fetchRole(roleId))
  const catalogueQuery = usePermissionCatalogue()

  if (isError) {
    return (
      <DetailError
        message={t('roles.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !role) {
    return <DetailLoading />
  }

  const canEdit = role.authorization.resource.update
  const createdAt = formatDateTime(role.created_at)
  const grantedAreas = catalogueQuery.data ? groupGrantedByArea(catalogueQuery.data.areas, role.permissions) : []
  const tabs: RecordCollaborationTab[] = role.authorization.actions.view_activity
    ? [activityLogTab('roles', role.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={role.name} icon={<ShieldCheck />} />}
            title={role.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection
              title={t('roles.form.permissions')}
              icon={<KeyRound />}
              action={role.permissions.length > 0 ? <Badge variant="secondary">{role.permissions.length}</Badge> : null}
              full
            >
              {role.permissions.length === 0 ? (
                <DetailEmpty />
              ) : catalogueQuery.isPending ? (
                <div className="flex flex-col gap-2" aria-hidden="true">
                  <Skeleton className="h-6 w-full" />
                  <Skeleton className="h-6 w-full" />
                </div>
              ) : catalogueQuery.isError ? (
                <p className="text-sm text-destructive" role="alert">
                  {t('authorization.loadError')}
                </p>
              ) : (
                <div className="flex flex-col gap-4">
                  {grantedAreas.map(({ area, resources }) => (
                    <div key={area.key} className="flex flex-col gap-2">
                      <span className="text-xs font-semibold text-muted-foreground">{t(area.label_key)}</span>
                      {resources.map(({ resource, granted }) => (
                        <div key={resource.resource} className="flex flex-col gap-1.5">
                          <span className="text-xs font-medium text-muted-foreground">{t(resource.label_key)}</span>
                          <div className="flex flex-wrap gap-1">
                            {granted.map((permission) => (
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
              )}
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('roles.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
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
 * within each, only the abilities) actually granted by this role — the same
 * taxonomy the permission explorer uses, applied read-only here.
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
