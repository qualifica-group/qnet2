import { z } from 'zod'
import type { TFunction } from 'i18next'
import { BADGE_COLOR_TOKENS } from '@/features/custom-fields/badge-color-tokens'
import { ICON_NAMES } from '@/features/custom-fields/icon-catalog'
import type { Workspace, WorkspacePayload } from '@/features/workspaces/types'

/** Server limits (spec 0317 data_contract). */
export const NAME_MAX_LENGTH = 60
export const SLUG_MAX_LENGTH = 40
export const DESCRIPTION_MAX_LENGTH = 160
const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/

const COLOR_TOKENS = BADGE_COLOR_TOKENS.map((entry) => entry.token)

/** Form schema of the app create/edit dialog; mirrors the server rules, which stay authoritative. */
export function buildWorkspaceSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('form.nameMax')),
    description: z.string().trim().max(DESCRIPTION_MAX_LENGTH, t('form.descriptionMax')),
    slug: z
      .string()
      .trim()
      .min(1, t('form.slugRequired'))
      .max(SLUG_MAX_LENGTH, t('form.slugMax'))
      .regex(SLUG_PATTERN, t('form.slugFormat')),
    icon: z.string().refine((value) => value === '' || ICON_NAMES.includes(value), t('form.iconInvalid')),
    color: z.string().refine((value) => value === '' || COLOR_TOKENS.includes(value), t('form.colorInvalid')),
    sort_order: z.number().int().min(0, t('form.sortOrderMin')).nullable(),
    navigation_keys: z.array(z.string()).min(1, t('form.entriesRequired')),
  })
}

export type WorkspaceFormValues = z.infer<ReturnType<typeof buildWorkspaceSchema>>

export const EMPTY_WORKSPACE_FORM: WorkspaceFormValues = {
  name: '',
  description: '',
  slug: '',
  icon: '',
  color: '',
  sort_order: 0,
  navigation_keys: [],
}

export function workspaceToFormValues(workspace: Workspace): WorkspaceFormValues {
  return {
    name: workspace.name,
    description: workspace.description ?? '',
    slug: workspace.slug,
    icon: workspace.icon ?? '',
    color: workspace.color ?? '',
    sort_order: workspace.sort_order,
    navigation_keys: workspace.navigation_keys,
  }
}

export function buildWorkspacePayload(values: WorkspaceFormValues): WorkspacePayload {
  return {
    name: values.name,
    description: values.description === '' ? null : values.description,
    slug: values.slug,
    icon: values.icon === '' ? null : values.icon,
    color: values.color === '' ? null : values.color,
    sort_order: values.sort_order,
    navigation_keys: values.navigation_keys,
  }
}
