import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { duplicateItems, isAlreadyPresent, isKept, type DiscardedItems } from '@/features/record-merge/merge-selection'
import type { MergeCollection, MergeCollectionKey, MergeRecord } from '@/features/record-merge/types'

interface MergeCollectionsProps {
  collections: MergeCollection[]
  records: MergeRecord[]
  survivorId: number
  discarded: DiscardedItems
  onToggle: (key: MergeCollectionKey, itemId: number) => void
}

/** The duplicates' contacts, addresses, notes... as checkboxes grouped by source record; unticked = discarded. */
export function MergeCollections({ collections, records, survivorId, discarded, onToggle }: MergeCollectionsProps) {
  const { t } = useTranslation()
  const visible = collections.filter((collection) => duplicateItems(collection, survivorId).length > 0)

  if (visible.length === 0) {
    return null
  }

  return (
    <section className="flex flex-col gap-2" aria-labelledby="merge-collections-title">
      <div>
        <h3 id="merge-collections-title" className="text-sm font-semibold">
          {t('recordMerge.collections.title')}
        </h3>
        <p className="text-xs text-muted-foreground">{t('recordMerge.collections.hint')}</p>
      </div>
      <div className="grid gap-2 md:grid-cols-2">
        {visible.map((collection) => (
          <CollectionCard
            key={collection.key}
            collection={collection}
            records={records}
            survivorId={survivorId}
            discarded={discarded}
            onToggle={onToggle}
          />
        ))}
      </div>
    </section>
  )
}

interface CollectionCardProps extends Omit<MergeCollectionsProps, 'collections'> {
  collection: MergeCollection
}

function CollectionCard({ collection, records, survivorId, discarded, onToggle }: CollectionCardProps) {
  const { t } = useTranslation()
  const items = duplicateItems(collection, survivorId)
  const groupLabel = t(`recordMerge.collections.labels.${collection.key}`, { defaultValue: collection.key })

  return (
    <fieldset className="flex min-w-0 flex-col gap-2 rounded-lg border bg-card p-3">
      <legend className="px-1 text-xs font-semibold">{groupLabel}</legend>
      {records
        .filter((record) => record.id !== survivorId)
        .map((record) => {
          const recordItems = items.filter((item) => item.record_id === record.id)
          if (recordItems.length === 0) {
            return null
          }
          return (
            <div key={record.id} className="flex flex-col gap-1">
              <p className="truncate text-xs font-medium text-muted-foreground" title={record.name}>
                {record.name}
              </p>
              <ul className="flex flex-col gap-1">
                {recordItems.map((item) => {
                  const selectable = !isAlreadyPresent(collection, item, survivorId)
                  const inputId = `merge-item-${collection.key}-${record.id}-${item.id}`
                  return (
                    <li key={item.id} className="flex min-w-0 items-start gap-2 text-xs">
                      {selectable ? (
                        <Checkbox
                          id={inputId}
                          aria-labelledby={`${inputId}-label`}
                          checked={isKept(discarded, collection.key, item.id)}
                          onCheckedChange={() => onToggle(collection.key, item.id)}
                          className="mt-0.5 size-3.5"
                        />
                      ) : (
                        <span className="size-3.5 shrink-0" aria-hidden="true" />
                      )}
                      <Label id={`${inputId}-label`} htmlFor={selectable ? inputId : undefined} className="min-w-0 flex-wrap text-xs font-normal">
                        <span className="break-words">{item.label}</span>
                        {item.detail ? <span className="text-muted-foreground">{item.detail}</span> : null}
                        {selectable ? null : <Badge variant="secondary">{t('recordMerge.collections.alreadyPresent')}</Badge>}
                      </Label>
                    </li>
                  )
                })}
              </ul>
            </div>
          )
        })}
    </fieldset>
  )
}
