import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { ModuleActionsPanel } from '@/features/roles/permission-explorer/module-actions-panel'
import { resourceSelectionCount } from '@/features/roles/permission-explorer/permission-selection'
import { RoleFieldPermissions } from '@/features/roles/role-field-permissions'
import type { PermissionCatalogueResource } from '@/features/roles/permission-catalogue-api'
import type { RoleFieldPermission } from '@/features/roles/types'
import type { FieldPermissionFlag } from '@/features/roles/field-permission-toggle'

export interface ModuleFieldPermissionsSlot {
  value: RoleFieldPermission[]
  disabled: boolean
  onToggle: (resource: string, field: string, flag: FieldPermissionFlag, checked: boolean) => void
}

interface ModuleDetailPanelProps {
  resource: PermissionCatalogueResource
  value: string[]
  disabled: boolean
  onTogglePermission: (name: string, checked: boolean) => void
  /** `null` hides the fields section entirely (actor cannot manage field permissions, AC-015 legacy gate). */
  fieldPermissions: ModuleFieldPermissionsSlot | null
}

/**
 * Right panel of the permission explorer (spec 0076, AC-011): the selected
 * module's actions and its fields (native + custom) in the SAME scheda — the
 * previously separate "Permessi campi" section is gone.
 */
export function ModuleDetailPanel({
  resource,
  value,
  disabled,
  onTogglePermission,
  fieldPermissions,
}: ModuleDetailPanelProps) {
  const { t } = useTranslation()
  const counts = resourceSelectionCount(resource, value)

  return (
    <div className="flex flex-col gap-4 rounded-lg border bg-card p-3">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-sm font-semibold">{t(resource.label_key)}</h3>
        <Badge variant="secondary">
          {t('roles.permissionExplorer.selectionCount', { selected: counts.selected, total: counts.total })}
        </Badge>
      </div>

      <ModuleActionsPanel
        resource={resource}
        value={value}
        disabled={disabled}
        onTogglePermission={onTogglePermission}
      />

      {fieldPermissions && (
        <>
          <Separator />
          <RoleFieldPermissions
            resource={resource.resource}
            fields={resource.fields}
            value={fieldPermissions.value}
            disabled={fieldPermissions.disabled}
            onToggle={fieldPermissions.onToggle}
          />
        </>
      )}
    </div>
  )
}
