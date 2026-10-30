import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { SupportRequestForm } from '@/features/support-requests/support-request-form'
import { SupportRequestSent } from '@/features/support-requests/support-request-sent'
import { useSupportRequestForm } from '@/features/support-requests/use-support-request-form'
import { useSupportRequestTopics } from '@/features/support-requests/use-support-request-topics'
import type { SupportRequestTopic } from '@/features/support-requests/types'

interface SupportRequestDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Stable empty list: a fresh `[]` per render would defeat memoised consumers. */
const NO_TOPICS: SupportRequestTopic[] = []

interface SupportRequestBodyProps {
  topics: SupportRequestTopic[]
  onClose: () => void
}

/** Mounted only while the dialog is open, so closing it discards the form and the confirmation. */
function SupportRequestBody({ topics, onClose }: SupportRequestBodyProps) {
  const state = useSupportRequestForm()

  return state.result ? (
    <SupportRequestSent result={state.result} onClose={onClose} />
  ) : (
    <SupportRequestForm topics={topics} state={state} />
  )
}

/** Technical support dialog (spec 0334): loading, error, "not configured", the form, then the confirmation. */
export function SupportRequestDialog({ open, onOpenChange }: SupportRequestDialogProps) {
  const { t } = useTranslation()
  const topicsQuery = useSupportRequestTopics(open)
  const topics = topicsQuery.data ?? NO_TOPICS

  const renderContent = () => {
    if (topicsQuery.isError) {
      return (
        <div className="grid justify-items-start gap-3">
          <p role="alert" className="text-sm text-destructive">
            {t('supportRequests.topicsLoadError')}
          </p>
          <Button variant="secondary" size="sm" onClick={() => void topicsQuery.refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      )
    }
    if (topicsQuery.isPending) {
      return <Skeleton className="h-24 w-full" />
    }
    if (topics.length === 0) {
      return <p className="text-sm text-muted-foreground">{t('supportRequests.notConfigured')}</p>
    }
    return <SupportRequestBody topics={topics} onClose={() => onOpenChange(false)} />
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="sm" className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('supportRequests.title')}</DialogTitle>
          <DialogDescription>{t('supportRequests.description')}</DialogDescription>
        </DialogHeader>
        {renderContent()}
      </DialogContent>
    </Dialog>
  )
}
