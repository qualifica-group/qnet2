import { SLUG_MAX_LENGTH } from '@/features/workspaces/workspace-schema'

/** URL address suggested from an app name: "Qualifica Contabilità" -> "qualifica-contabilita". */
export function slugify(name: string): string {
  return name
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, SLUG_MAX_LENGTH)
    .replace(/-+$/, '')
}
