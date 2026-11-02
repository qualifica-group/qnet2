import { useTranslation } from 'react-i18next'
import { useDefaultStatuses } from '@/features/quote-workflows/use-default-statuses'
import { useQuoteStatusGroups } from '@/features/quote-workflows/use-quote-status-groups'
import { DefaultStatusesSheet } from '@/features/workflow-statuses/default-statuses-sheet'

interface QuoteDefaultStatusesSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** The GLOBAL quote default status set: the shared sheet fed by `GET`/`PUT /quote-workflows/default-statuses`. */
export function QuoteDefaultStatusesSheet({ open, onOpenChange }: QuoteDefaultStatusesSheetProps) {
  const { t } = useTranslation()
  const groups = useQuoteStatusGroups()
  const state = useDefaultStatuses({
    enabled: open,
    labels: {
      saved: t('quoteWorkflows.defaultStatuses.saved'),
      genericError: t('quoteWorkflows.defaultStatuses.genericError'),
      nameRequired: t('quoteWorkflows.form.statuses.nameRequired'),
    },
  })

  return (
    <DefaultStatusesSheet
      open={open}
      onOpenChange={onOpenChange}
      state={state}
      groups={groups}
      showRequiresNote
      storageKey="sheet-width:quote-workflows-default-statuses"
      labels={{
        title: t('quoteWorkflows.defaultStatuses.title'),
        subtitle: t('quoteWorkflows.defaultStatuses.subtitle'),
        loadError: t('quoteWorkflows.defaultStatuses.loadError'),
        cancel: t('quoteWorkflows.form.cancel'),
        save: t('quoteWorkflows.form.save'),
        saving: t('quoteWorkflows.form.saving'),
      }}
    />
  )
}
