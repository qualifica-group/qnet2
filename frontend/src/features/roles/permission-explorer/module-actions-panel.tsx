import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { LabeledCheckbox } from '@/features/roles/permission-explorer/checkbox-controls'
import { PRIMARY_ABILITIES } from '@/features/roles/permission-explorer/primary-abilities'
import { abilityLabel, permissionAbility, resourceLabel } from '@/features/roles/permission-labels'
import type { PermissionCatalogueResource } from '@/features/roles/permission-catalogue-api'

interface ModuleActionsPanelProps {
  resource: PermissionCatalogueResource
  value: string[]
  disabled: boolean
  onTogglePermission: (name: string, checked: boolean) => void
}

/**
 * The selected module's actions (spec 0076): the common CRUD abilities
 * (`PRIMARY_ABILITIES`) as pills, always visible; anything else (`export`,
 * `import`, …) collapses into an "advanced configuration" disclosure. Moved
 * from the retired `PermissionDomainCard`.
 */
export function ModuleActionsPanel({ resource, value, disabled, onTogglePermission }: ModuleActionsPanelProps) {
  const { t, i18n } = useTranslation()

  const primary = resource.permissions.filter((permission) =>
    PRIMARY_ABILITIES.includes(permissionAbility(permission.name)),
  )
  const advanced = resource.permissions.filter(
    (permission) => !PRIMARY_ABILITIES.includes(permissionAbility(permission.name)),
  )
  const moduleLabel = resourceLabel(resource.resource, i18n)

  return (
    <div className="flex flex-col gap-3">
      <h4 className="text-xs font-semibold text-muted-foreground">{t('roles.permissionExplorer.actionsHeading')}</h4>

      {primary.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {primary.map((permission) => (
            <AbilityPill
              key={permission.name}
              checked={value.includes(permission.name)}
              disabled={disabled}
              label={abilityLabel(permission.name, i18n)}
              ariaLabel={`${moduleLabel} — ${abilityLabel(permission.name, i18n)}`}
              onChange={(checked) => onTogglePermission(permission.name, checked)}
            />
          ))}
        </div>
      )}

      {advanced.length > 0 && (
        <Collapsible>
          <CollapsibleTrigger className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50 [&[data-state=open]>svg]:rotate-180">
            <ChevronDown className="size-3.5 transition-transform" aria-hidden="true" />
            {t('roles.form.advanced')}
          </CollapsibleTrigger>
          <CollapsibleContent className="mt-2 flex flex-col gap-1.5 border-t pt-2">
            <span className="text-xs font-medium text-muted-foreground">{t('roles.form.advancedActions')}</span>
            <div className="flex flex-wrap gap-2">
              {advanced.map((permission) => (
                <AbilityPill
                  key={permission.name}
                  checked={value.includes(permission.name)}
                  disabled={disabled}
                  label={abilityLabel(permission.name, i18n)}
                  ariaLabel={`${moduleLabel} — ${abilityLabel(permission.name, i18n)}`}
                  onChange={(checked) => onTogglePermission(permission.name, checked)}
                />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      )}
    </div>
  )
}

interface AbilityPillProps {
  checked: boolean
  disabled: boolean
  label: string
  ariaLabel: string
  onChange: (checked: boolean) => void
}

/** One ability toggle rendered as a checkbox pill, visually distinct when granted. */
function AbilityPill({ checked, disabled, label, ariaLabel, onChange }: AbilityPillProps) {
  return (
    <LabeledCheckbox
      checked={checked}
      disabled={disabled}
      label={label}
      ariaLabel={ariaLabel}
      onChange={onChange}
      className={cn(
        'rounded-md border px-2 py-1 text-xs font-normal',
        checked ? 'border-primary/40 bg-primary/5 text-foreground' : 'border-border text-muted-foreground',
      )}
    />
  )
}
