import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CircleCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { SupportRequestResult } from '@/features/support-requests/types'

interface SupportRequestSentProps {
  result: SupportRequestResult
  onClose: () => void
}

/** Confirmation screen: the Task number and, only when the actor may see Tasks, a link to it (spec 0334 D-7). */
export function SupportRequestSent({ result, onClose }: SupportRequestSentProps) {
  const { t } = useTranslation()

  return (
    <div className="grid justify-items-center gap-3 py-4 text-center">
      <CircleCheck className="size-8 text-primary" aria-hidden="true" />
      <p role="status" className="text-base font-semibold">
        {t('supportRequests.sentTitle', { id: result.task_id })}
      </p>
      <p className="text-sm text-muted-foreground">{t('supportRequests.sentDescription')}</p>
      <div className="flex flex-wrap justify-center gap-2">
        {result.can_view_task ? (
          <Button asChild>
            <Link to={`/tasks/${result.task_id}`} onClick={onClose}>
              {t('supportRequests.openTask')}
            </Link>
          </Button>
        ) : null}
        <Button type="button" variant="secondary" onClick={onClose}>
          {t('supportRequests.close')}
        </Button>
      </div>
    </div>
  )
}
