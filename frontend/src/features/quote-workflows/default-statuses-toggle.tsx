import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Settings2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { DefaultStatusesSheet } from '@/features/quote-workflows/default-statuses-sheet'

/**
 * Toolbar affordance opening the GLOBAL default status set editor (spec 0047
 * Lane C), gated by `quote-workflows.update` — mirrors
 * `StatusReorderToggle`'s split between the gated button and the owned
 * open/closed state.
 */
export function DefaultStatusesToggle() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <Can permission="quote-workflows.update">
      <Button variant="outline" onClick={() => setOpen(true)}>
        <Settings2 aria-hidden="true" />
        {t('quoteWorkflows.defaultStatuses.openButton')}
      </Button>
      <DefaultStatusesSheet open={open} onOpenChange={setOpen} />
    </Can>
  )
}
