import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { KeySquare } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { usePermissionCatalogue } from '@/features/roles/use-permission-catalogue'
import { PermissionExplorer } from '@/features/roles/permission-explorer/permission-explorer'
import { LabeledCheckbox } from '@/features/roles/permission-explorer/checkbox-controls'
import { allPermissionNames } from '@/features/roles/permission-explorer/permission-selection'
import { toggleFieldPermission } from '@/features/roles/field-permission-toggle'
import type { PermissionCatalogueArea } from '@/features/roles/permission-catalogue-api'
import type { RoleFormValues } from '@/features/roles/use-role-form'

interface PermissionsSectionProps {
  control: Control<RoleFormValues>
  /** Whether the actor may manage the role's field-permission matrix at all (spec 0006 gate). */
  canManageFieldPermissions: boolean
}

/**
 * The role form's "Permissions" section (spec 0076): fetches the Area >
 * Module catalogue, wraps the `permissions` field in `MetaField` (disables
 * the whole explorer when the actor cannot write the role) and, when the
 * actor may manage field permissions, layers a second `MetaField` for
 * `field_permissions` around the module panel's fields subsection — the same
 * two-gate combination the retired separate "Permessi campi" section used,
 * now feeding a single unified `<PermissionExplorer>`.
 */
export function PermissionsSection({ control, canManageFieldPermissions }: PermissionsSectionProps) {
  const { t } = useTranslation()
  const catalogueQuery = usePermissionCatalogue()
  const areas: PermissionCatalogueArea[] = catalogueQuery.data?.areas ?? []

  return (
    <MetaField control={control} name="permissions" metaKey="permissions" label="">
      {({ field: permissionsField, disabled: permissionsDisabled }) => (
        <FormSection
          icon={KeySquare}
          title={t('roles.form.sections.permissions.title')}
          description={t('roles.form.sections.permissions.description')}
          aside={
            areas.length > 0 ? (
              <LabeledCheckbox
                checked={allSelected(allPermissionNames(areas), permissionsField.value)}
                disabled={permissionsDisabled}
                label={t('roles.form.selectAllGlobal')}
                className="text-xs font-medium text-muted-foreground"
                onChange={(checked) => permissionsField.onChange(checked ? allPermissionNames(areas) : [])}
              />
            ) : null
          }
        >
          {catalogueQuery.isPending ? (
            <div className="flex flex-col gap-2" aria-hidden="true">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-24 w-full" />
            </div>
          ) : catalogueQuery.isError ? (
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm text-destructive" role="alert">
                {t('authorization.loadError')}
              </p>
              <Button type="button" variant="outline" size="sm" onClick={() => catalogueQuery.refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : areas.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('roles.form.noPermissions')}</p>
          ) : canManageFieldPermissions ? (
            <MetaField control={control} name="field_permissions" metaKey="field_permissions" label="">
              {({ field: fieldPermissionsField, disabled: fieldPermissionsDisabled }) => (
                <PermissionExplorer
                  areas={areas}
                  value={permissionsField.value}
                  disabled={permissionsDisabled}
                  onChange={permissionsField.onChange}
                  fieldPermissions={{
                    value: fieldPermissionsField.value,
                    disabled: fieldPermissionsDisabled,
                    onToggle: (resource, field, flag, checked) =>
                      fieldPermissionsField.onChange(
                        toggleFieldPermission(fieldPermissionsField.value, resource, field, flag, checked),
                      ),
                  }}
                />
              )}
            </MetaField>
          ) : (
            <PermissionExplorer
              areas={areas}
              value={permissionsField.value}
              disabled={permissionsDisabled}
              onChange={permissionsField.onChange}
              fieldPermissions={null}
            />
          )}
        </FormSection>
      )}
    </MetaField>
  )
}

function allSelected(names: string[], value: string[]): boolean {
  return names.length > 0 && names.every((name) => value.includes(name))
}
