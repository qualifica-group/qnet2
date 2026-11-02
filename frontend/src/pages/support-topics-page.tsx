import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { SupportTopicsTable } from '@/features/support-topics/support-topics-table'

/** Support topics page (spec 0334): gates access with `support-topics.viewAny` and mounts the thin table adapter. */
export default function SupportTopicsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="support-topics.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('supportTopics.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <SupportTopicsTable />
      </div>
    </Can>
  )
}
