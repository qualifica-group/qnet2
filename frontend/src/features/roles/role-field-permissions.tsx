import { useTranslation } from 'react-i18next'
import { AriaCheckbox } from '@/features/roles/permission-explorer/checkbox-controls'
import { catalogueFieldLabel } from '@/features/roles/permission-labels'
import type { FieldPermissionFlag } from '@/features/roles/field-permission-toggle'
import type { PermissionCatalogueField } from '@/features/roles/permission-catalogue-api'
import type { RoleFieldPermission } from '@/features/roles/types'

type ToggleFieldPermission = (
  resource: string,
  field: string,
  flag: FieldPermissionFlag,
  checked: boolean,
) => void

interface RoleFieldPermissionsProps {
  resource: string
  /** The selected module's own fields (native + custom), from the permission catalogue. */
  fields: PermissionCatalogueField[]
  value: RoleFieldPermission[]
  onToggle: ToggleFieldPermission
  disabled: boolean
}

/**
 * The selected module's field-permission matrix (spec 0006, unified into the
 * module's own scheda by spec 0076): three toggles (visible / editable /
 * required) per field, expressing a DB-driven RESTRICTION within the 0004
 * code ceiling — never an escalation, enforced server-side. Native and
 * custom fields render under two separate headings (AC-011) but share the
 * same grid so the trio columns line up. UI-only: value/toggling logic
 * lives in the caller (`useRoleForm` + `field-permission-toggle.ts`).
 */
export function RoleFieldPermissions({ resource, fields, value, onToggle, disabled }: RoleFieldPermissionsProps) {
  const { t } = useTranslation()

  if (fields.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('roles.fieldPermissions.empty')}</p>
  }

  const native = fields.filter((field) => !field.custom)
  const custom = fields.filter((field) => field.custom)

  return (
    <div className="flex flex-col gap-4">
      <h4 className="text-xs font-semibold text-muted-foreground">{t('roles.permissionExplorer.fieldsHeading')}</h4>

      {native.length > 0 && (
        <FieldGroup
          heading={t('roles.permissionExplorer.nativeFieldsLabel')}
          resource={resource}
          fields={native}
          value={value}
          disabled={disabled}
          onToggle={onToggle}
        />
      )}

      {custom.length > 0 && (
        <FieldGroup
          heading={t('roles.permissionExplorer.customFieldsLabel')}
          resource={resource}
          fields={custom}
          value={value}
          disabled={disabled}
          onToggle={onToggle}
        />
      )}
    </div>
  )
}

interface FieldGroupProps {
  heading: string
  resource: string
  fields: PermissionCatalogueField[]
  value: RoleFieldPermission[]
  disabled: boolean
  onToggle: ToggleFieldPermission
}

/** One native/custom group: its own subheading, then the visible/editable/required grid. */
function FieldGroup({ heading, resource, fields, value, disabled, onToggle }: FieldGroupProps) {
  const { t, i18n } = useTranslation()

  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-xs font-medium text-muted-foreground">{heading}</span>
      <div className="overflow-x-auto">
        <div className="grid min-w-[420px] grid-cols-[1fr_auto_auto_auto] items-center gap-x-3 gap-y-1.5">
          <span />
          <span className="text-center text-xs text-muted-foreground">{t('roles.fieldPermissions.visible')}</span>
          <span className="text-center text-xs text-muted-foreground">{t('roles.fieldPermissions.editable')}</span>
          <span className="text-center text-xs text-muted-foreground">{t('roles.fieldPermissions.required')}</span>
          {fields.map((field) => {
            const rowValue = value.find((row) => row.resource === resource && row.field === field.key)
            return (
              <FieldRow
                key={field.key}
                resource={resource}
                fieldKey={field.key}
                label={catalogueFieldLabel(resource, field, i18n)}
                visible={rowValue?.visible ?? true}
                editable={rowValue?.editable ?? true}
                required={rowValue?.required ?? false}
                mandatory={field.mandatory}
                disabled={disabled}
                onToggle={onToggle}
              />
            )
          })}
        </div>
      </div>
    </div>
  )
}

interface FieldRowProps {
  resource: string
  fieldKey: string
  label: string
  visible: boolean
  editable: boolean
  required: boolean
  mandatory: boolean
  disabled: boolean
  onToggle: ToggleFieldPermission
}

/** One field's label + three toggles, as grid cells (a fragment, not a row element). */
function FieldRow({
  resource,
  fieldKey,
  label,
  visible,
  editable,
  required,
  mandatory,
  disabled,
  onToggle,
}: FieldRowProps) {
  const { t } = useTranslation()

  // Mandatory fields (spec 0008) are vital to creating the resource: a role can
  // never restrict them, so all three checkboxes are forced on and disabled —
  // the client twin of the server-side merge that ignores their DB config.
  const locked = disabled || mandatory

  return (
    <>
      <span className="truncate text-sm" title={mandatory ? t('roles.fieldPermissions.mandatory') : undefined}>
        {label}
        {mandatory ? <span className="text-muted-foreground"> *</span> : null}
      </span>
      <FieldToggle
        checked={mandatory ? true : visible}
        disabled={locked}
        label={`${label} — ${t('roles.fieldPermissions.visible')}`}
        onChange={(checked) => onToggle(resource, fieldKey, 'visible', checked)}
      />
      <FieldToggle
        checked={mandatory ? true : editable}
        disabled={locked}
        label={`${label} — ${t('roles.fieldPermissions.editable')}`}
        onChange={(checked) => onToggle(resource, fieldKey, 'editable', checked)}
      />
      <FieldToggle
        // `required` is only meaningful when `editable` (spec 0006 merge rule).
        checked={mandatory ? true : required}
        disabled={locked || !editable}
        label={`${label} — ${t('roles.fieldPermissions.required')}`}
        onChange={(checked) => onToggle(resource, fieldKey, 'required', checked)}
      />
    </>
  )
}

interface FieldToggleProps {
  checked: boolean
  disabled: boolean
  label: string
  onChange: (checked: boolean) => void
}

function FieldToggle({ checked, disabled, label, onChange }: FieldToggleProps) {
  return (
    <span className="flex items-center justify-center">
      <AriaCheckbox checked={checked} disabled={disabled} label={label} onChange={onChange} />
    </span>
  )
}
