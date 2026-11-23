import { useState } from 'react'
import { PlugZap } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { ArchivioSmartConnectionDialog } from '@/features/document-checklists/archivio-smart-connection-dialog'

const MANAGE_CONNECTION_PERMISSION = 'document-checklists.manageConnection'

/** Toolbar button opening the connection dialog; self-gated on `document-checklists.manageConnection` (UX only). */
export function ArchivioSmartConnectionButton() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <Can permission={MANAGE_CONNECTION_PERMISSION}>
      <Button type="button" size="sm" variant="secondary" onClick={() => setOpen(true)}>
        <PlugZap aria-hidden="true" />
        {t('documentChecklists.actions.connection')}
      </Button>
      <ArchivioSmartConnectionDialog open={open} onOpenChange={setOpen} />
    </Can>
  )
}
