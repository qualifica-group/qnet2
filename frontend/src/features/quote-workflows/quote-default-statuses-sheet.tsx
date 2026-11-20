import { useTranslation } from 'react-i18next'
import { useDefaultStatuses } from '@/features/quote-workflows/use-default-statuses'
import { useQuoteSystemStatusOptions } from '@/features/quote-system-statuses/use-quote-system-status-options'
import { DefaultStatusesSheet } from '@/features/workflow-statuses/default-statuses-sheet'

interface QuoteDefaultStatusesSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** The GLOBAL quote default status set: the shared sheet fed by `GET`/`PUT /quote-workflows/default-statuses`. */
export function QuoteDefaultStatusesSheet({ open, onOpenChange }: QuoteDefaultStatusesSheetProps) {
  const { t } = useTranslation()
  const { options } = useQuoteSystemStatusOptions(open)
  const state = useDefaultStatuses({
    enabled: open,
    labels: {
      saved: t('quoteWorkflows.defaultStatuses.saved'),
      genericError: t('quoteWorkflows.defaultStatuses.genericError'),
      nameRequired: t('quoteWorkflows.form.statuses.nameRequired'),
      systemStatusRequired: t('quoteWorkflows.form.statuses.systemStatusRequired'),
    },
  })

  return (
    <DefaultStatusesSheet
      open={open}
      onOpenChange={onOpenChange}
      state={state}
      systemStatuses={options}
      showRequiresNote
      showNotify
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
