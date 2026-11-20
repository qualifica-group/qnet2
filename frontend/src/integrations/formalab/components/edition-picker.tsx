import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useFormalabClasses } from '@/integrations/formalab/use-formalab-work-order-link'

interface EditionPickerProps {
  /** Label of the confirm button ("Collega" / "Cambia edizione"). */
  submitLabel: string
  pending: boolean
  onSubmit: (editionId: number) => void
  onCancel?: () => void
}

/** Server-side searchable edition select plus its confirm button. */
export function EditionPicker({ submitLabel, pending, onSubmit, onCancel }: EditionPickerProps) {
  const { t } = useTranslation('formalab')
  const [search, setSearch] = useState('')
  const [editionId, setEditionId] = useState<number | null>(null)
  const classes = useFormalabClasses(search, true)

  const options = (classes.data ?? []).map((item) => ({ id: item.edition_id, name: item.label }))

  return (
    <div className="flex flex-wrap items-center gap-2">
      <SearchableSelect
        className="w-full max-w-md"
        value={editionId}
        onChange={setEditionId}
        options={options}
        filter={false}
        onSearchChange={setSearch}
        isPending={classes.isPending}
        isError={classes.isError}
        onRetry={() => void classes.refetch()}
        labels={{
          placeholder: t('workOrder.pickerPlaceholder'),
          searchPlaceholder: t('workOrder.pickerSearch'),
          empty: t('workOrder.pickerEmpty'),
          noMatch: t('workOrder.pickerNoMatch'),
          error: t('workOrder.pickerError'),
          retry: t('workOrder.pickerRetry'),
          triggerLabel: t('workOrder.pickerLabel'),
        }}
      />
      <Button size="sm" disabled={editionId === null || pending} onClick={() => editionId !== null && onSubmit(editionId)}>
        {submitLabel}
      </Button>
      {onCancel ? (
        <Button size="sm" variant="secondary" onClick={onCancel}>
          {t('workOrder.cancel')}
        </Button>
      ) : null}
    </div>
  )
}
