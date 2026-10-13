import { createElement } from 'react'
import { Braces } from 'lucide-react'
import type { ApiModule } from '@/features/api-integrations/api-modules'
import { resolveIcon } from '@/features/navigation/icon-map'

/** The menu icon of the module, a generic one when it has no menu entry. */
export function ModuleIcon({ module, className }: { module: ApiModule; className?: string }) {
  return createElement(module.icon === null ? Braces : resolveIcon(module.icon), {
    className,
    'aria-hidden': true,
  })
}
