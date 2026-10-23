import { useTranslation } from 'react-i18next'
import { ControlledChoiceDialog } from '@/components/controlled-choice-dialog'
import type { PropagationChoice } from './use-commission-configuration-propagation'
import type { CommissionConfigurationQuoteImpact } from './types'

/** Not dismissible: the dialog never asks to close, the answer does. */
const IGNORE_OPEN_CHANGE = () => undefined

interface CommissionConfigurationPropagationDialogProps {
  impact: CommissionConfigurationQuoteImpact | null
  isPending: boolean
  onAnswer: (choice: PropagationChoice) => void
}

/**
 * The question asked after a rule is saved (spec 0308 D-6). The rule is saved
 * in every case; the dialog cannot be dismissed, an answer is required.
 */
export function CommissionConfigurationPropagationDialog({
  impact,
  isPending,
  onAnswer,
}: CommissionConfigurationPropagationDialogProps) {
  const { t } = useTranslation()
  const manual = impact?.manual_commissions_count ?? 0

  return (
    <ControlledChoiceDialog<PropagationChoice>
      open={impact !== null}
      dismissible={false}
      title={t('commissionConfigurations.propagation.title')}
      description={t('commissionConfigurations.propagation.description')}
      summary={
        impact
          ? t('commissionConfigurations.propagation.summary', {
              quotes: impact.quotes_count,
              lines: impact.lines_count,
              manual: impact.manual_commissions_count,
            })
          : null
      }
      choiceLabel={t('commissionConfigurations.propagation.choiceLabel')}
      options={[
        {
          value: 'NONE',
          label: t('commissionConfigurations.propagation.none'),
          description: t('commissionConfigurations.propagation.noneHint'),
        },
        { value: 'KEEP_MANUAL', label: t('commissionConfigurations.propagation.keepManual', { count: manual }) },
        { value: 'REGENERATE_ALL', label: t('commissionConfigurations.propagation.regenerateAll', { count: manual }) },
      ]}
      confirmLabel={t('commissionConfigurations.propagation.confirm')}
      pending={isPending}
      onConfirm={onAnswer}
      onOpenChange={IGNORE_OPEN_CHANGE}
    />
  )
}
