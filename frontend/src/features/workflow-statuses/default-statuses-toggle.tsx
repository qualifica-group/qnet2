import { useState, type ReactNode } from 'react'
import { Settings2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'

interface DefaultStatusesToggleProps {
  /** Permission gating the button, e.g. `quote-workflows.update`. */
  permission: string
  label: string
  /** Renders the domain's own sheet; the toggle owns only the open/closed state. */
  renderSheet: (sheet: { open: boolean; onOpenChange: (open: boolean) => void }) => ReactNode
}

/**
 * Toolbar affordance opening the GLOBAL default status set editor (spec 0047
 * Lane C), gated by the domain's update permission — mirrors
 * `StatusReorderToggle`'s split between the gated button and the owned
 * open/closed state.
 */
export function DefaultStatusesToggle({ permission, label, renderSheet }: DefaultStatusesToggleProps) {
  const [open, setOpen] = useState(false)

  return (
    <Can permission={permission}>
      <Button variant="outline" onClick={() => setOpen(true)}>
        <Settings2 aria-hidden="true" />
        {label}
      </Button>
      {renderSheet({ open, onOpenChange: setOpen })}
    </Can>
  )
}
