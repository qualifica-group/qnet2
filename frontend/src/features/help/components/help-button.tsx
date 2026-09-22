import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CircleHelp } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { HelpPanel } from '@/features/help/components/help-panel'

/**
 * Header entry point for the in-app guide (spec 0143, AC-001): a compact icon
 * button, same shape/placement convention as `NotificationBell`/`ThemeToggle`
 * it sits next to. Rendered as the panel's real `SheetTrigger` so Radix
 * returns focus to it on close (AC-010).
 */
export function HelpButton() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <HelpPanel
      open={open}
      onOpenChange={setOpen}
      trigger={
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('help.openButton')}
          className="size-7 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground [&_svg]:size-3.5"
        >
          <CircleHelp />
          <span className="sr-only">{t('help.openButton')}</span>
        </Button>
      }
    />
  )
}
