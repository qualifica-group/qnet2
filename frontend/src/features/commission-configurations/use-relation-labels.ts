import { useTranslation } from 'react-i18next'

/** The shared labels of every relation picker of the module. */
export function useRelationLabels() {
  const { t } = useTranslation()
  return {
    placeholder: t('commissionConfigurations.form.selectPlaceholder'),
    emptyLabel: t('commissionConfigurations.form.selectEmpty'),
    errorLabel: t('commissionConfigurations.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}
