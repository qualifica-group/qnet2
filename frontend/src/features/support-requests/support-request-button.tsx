import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { LifeBuoy } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { SupportRequestDialog } from '@/features/support-requests/support-request-dialog'

/**
 * Header entry point of the technical support request (spec 0334): same
 * compact icon-button shape as `HelpButton`, placed right before it.
 */
export function SupportRequestButton() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <>
      <Button
        variant="ghost"
        size="icon"
        aria-label={t('supportRequests.openButton')}
        className="size-7 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground [&_svg]:size-3.5"
        onClick={() => setOpen(true)}
      >
        <LifeBuoy />
        <span className="sr-only">{t('supportRequests.openButton')}</span>
      </Button>
      <SupportRequestDialog open={open} onOpenChange={setOpen} />
    </>
  )
}
