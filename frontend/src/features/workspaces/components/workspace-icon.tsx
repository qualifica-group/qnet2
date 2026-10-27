import type { LucideProps } from 'lucide-react'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { ICON_CATALOG } from '@/features/custom-fields/icon-catalog'

const FALLBACK_ICON = 'layout-grid'

/** App icon from the curated catalogue; a missing or no longer catalogued name shows a generic grid. */
export function WorkspaceIcon({ name, ...props }: Omit<LucideProps, 'ref' | 'name'> & { name: string | null }) {
  return <DynamicIcon name={name && ICON_CATALOG[name] ? name : FALLBACK_ICON} {...props} />
}
