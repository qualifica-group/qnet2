import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { SupportTopicFormBody } from '@/features/support-topics/support-topic-form-body'
import type { SupportTopicDetail } from '@/features/support-topics/types'

interface SupportTopicFormProps {
  onSuccess: (supportTopic: SupportTopicDetail) => void
  onCancel: () => void
}

/** The support topic create form (the detail edits in place): resolves `GET /meta/support-topics` first. */
export function SupportTopicForm(props: SupportTopicFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('support-topics')

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <SupportTopicFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
