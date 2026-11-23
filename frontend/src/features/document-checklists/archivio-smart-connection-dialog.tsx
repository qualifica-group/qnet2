import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { ArchivioSmartConnectionForm } from '@/features/document-checklists/archivio-smart-connection-form'
import { useArchivioSmartConnection } from '@/features/document-checklists/use-archivio-smart-connection'

interface ArchivioSmartConnectionDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Installation-wide ArchivioSmart connection (spec 0403 D-2): base URL, write-only token, connection test. */
export function ArchivioSmartConnectionDialog({ open, onOpenChange }: ArchivioSmartConnectionDialogProps) {
  const { t } = useTranslation()
  const connection = useArchivioSmartConnection(open)
  const { settings } = connection

  let body = <Skeleton className="h-24 w-full" />
  if (settings.isError) {
    body = (
      <p role="alert" className="text-sm text-destructive">
        {t('documentChecklists.connection.loadError')}
      </p>
    )
  } else if (settings.data) {
    body = (
      <ArchivioSmartConnectionForm
        settings={settings.data}
        connection={connection}
        onClose={() => onOpenChange(false)}
      />
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('documentChecklists.connection.title')}</DialogTitle>
          <DialogDescription>{t('documentChecklists.connection.description')}</DialogDescription>
        </DialogHeader>
        {body}
      </DialogContent>
    </Dialog>
  )
}
