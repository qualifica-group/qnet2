import { lazy } from 'react'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PROJECTS_FOR_SELECT_RESOURCE } from '@/features/projects/for-select-api'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'

/**
 * Quick-create entries whose ref label isn't a single detail field, or whose
 * form needs a prop beyond `mode`/`onSuccess`/`onCancel`. Kept apart from
 * `module-entries.tsx` so that file stays a flat "one field = the label"
 * list.
 */

const operationalSites: QuickCreateEntry = {
  titleKey: 'operationalSites.form.createTitle',
  descriptionKey: 'operationalSites.form.createSubtitle',
  permission: 'operational-sites.create',
  form: lazy(async () => {
    const { OperationalSiteForm } = await import('@/features/operational-sites/operational-site-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <OperationalSiteForm
          mode={{ type: 'create' }}
          onSuccess={(site) =>
            onSuccess({
              id: site.id,
              // Mirrors OperationalSiteForSelectResource::composeLabel: a site has
              // no own name, its label is `line1` plus " - {city}" when known.
              name: site.city ? `${site.line1} - ${site.city.name}` : site.line1,
            })
          }
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const projects: QuickCreateEntry = {
  titleKey: 'projects.form.createTitle',
  descriptionKey: 'projects.form.createSubtitle',
  permission: 'projects.create',
  form: lazy(async () => {
    const { ProjectForm } = await import('@/features/projects/project-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <ProjectForm
          mode={{ type: 'create' }}
          // Mirrors ProjectForSelectResource: label = "{code} — {name}".
          onSuccess={(project) => onSuccess({ id: project.id, name: `${project.code} — ${project.name}` })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const roles: QuickCreateEntry = {
  titleKey: 'roles.form.createTitle',
  descriptionKey: 'roles.form.createSubtitle',
  permission: 'roles.create',
  form: lazy(async () => {
    const { RoleForm } = await import('@/features/roles/role-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <RoleForm
          mode={{ type: 'create' }}
          onSuccess={(role) => onSuccess({ id: role.id, name: role.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

/** resource -> entry, for the modules covered by this file. */
export const advancedEntries: Record<string, QuickCreateEntry> = {
  [OPERATIONAL_SITES_FOR_SELECT_RESOURCE]: operationalSites,
  [PROJECTS_FOR_SELECT_RESOURCE]: projects,
  [ROLES_FOR_SELECT_RESOURCE]: roles,
}
