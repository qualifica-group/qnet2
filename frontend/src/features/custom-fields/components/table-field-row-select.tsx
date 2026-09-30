import { useTranslation } from 'react-i18next'

interface TableFieldRowSelectProps {
  /** Shared by every radio of the field so the browser treats them as one group. */
  groupName: string
  index: number
  checked: boolean
  disabled: boolean
  onSelect: () => void
}

/** The "only one row" radio of a `selectable` table; native radios share `name`, so picking one clears the others. */
export function TableFieldRowSelect({ groupName, index, checked, disabled, onSelect }: TableFieldRowSelectProps) {
  const { t } = useTranslation()

  return (
    <input
      type="radio"
      name={groupName}
      className="size-3.5"
      aria-label={t('customFields.tableField.selectRow', { index: index + 1 })}
      checked={checked}
      disabled={disabled}
      onChange={onSelect}
    />
  )
}
