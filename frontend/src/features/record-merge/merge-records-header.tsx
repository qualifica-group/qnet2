import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { formatDate } from '@/features/record-merge/merge-format'
import type { MergeRecord } from '@/features/record-merge/types'

interface MergeRecordsHeaderProps {
  records: MergeRecord[]
  survivorId: number
  suggestedId: number
  onSelect: (id: number) => void
}

/** The records as columns, each with the "keep this one" radio that elects the survivor. */
export function MergeRecordsHeader({ records, survivorId, suggestedId, onSelect }: MergeRecordsHeaderProps) {
  const { t, i18n } = useTranslation()

  return (
    <RadioGroup
      aria-label={t('recordMerge.recordsLabel')}
      value={String(survivorId)}
      onValueChange={(value) => onSelect(Number(value))}
      className="grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
    >
      {records.map((record) => {
        const inputId = `merge-survivor-${record.id}`
        const isSurvivor = record.id === survivorId
        return (
          <div
            key={record.id}
            className={`flex min-w-0 flex-col gap-1 rounded-lg border p-3 ${isSurvivor ? 'border-primary bg-card' : 'bg-card'}`}
          >
            <div className="flex items-start justify-between gap-2">
              <span className="min-w-0 truncate text-sm font-semibold" title={record.name}>
                {record.name}
              </span>
              {record.id === suggestedId ? <Badge variant="secondary">{t('recordMerge.suggested')}</Badge> : null}
            </div>
            <p className="text-xs text-muted-foreground">
              {t('recordMerge.recordId', { id: record.id })}
              {' · '}
              {t('recordMerge.recordCreatedAt', { date: formatDate(record.created_at, i18n.language) })}
              {record.old_id !== null ? ` · ${t('recordMerge.recordOldId', { id: record.old_id })}` : ''}
            </p>
            <div className="mt-1 flex items-center gap-2">
              <RadioGroupItem id={inputId} value={String(record.id)} aria-labelledby={`${inputId}-label`} />
              <Label id={`${inputId}-label`} htmlFor={inputId} className="text-xs">
                {t('recordMerge.keepThis')}{' '}
                <span className="sr-only">{record.name}</span>
              </Label>
            </div>
          </div>
        )
      })}
    </RadioGroup>
  )
}
