import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button, buttonVariants } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { MergeCollections } from '@/features/record-merge/merge-collections'
import { MergeFieldsTable } from '@/features/record-merge/merge-fields-table'
import { MergeLinked } from '@/features/record-merge/merge-linked'
import { MergeRecordsHeader } from '@/features/record-merge/merge-records-header'
import { useRecordMerge } from '@/features/record-merge/use-record-merge'
import type { MergeModule } from '@/features/record-merge/types'

interface RecordMergeDialogProps {
  module: MergeModule
  ids: readonly number[]
  onClose: () => void
  onMerged: () => void
}

/** "Unisci duplicati" (spec 0394): preview, survivor and value choices, discards, then a destructive confirm. */
export function RecordMergeDialog({ module, ids, onClose, onMerged }: RecordMergeDialogProps) {
  const { t } = useTranslation()
  const [confirmOpen, setConfirmOpen] = useState(false)
  const merge = useRecordMerge({
    module,
    ids,
    onMerged: () => {
      onMerged()
      onClose()
    },
  })
  const data = merge.preview.data

  return (
    <Dialog open onOpenChange={(open) => (open || merge.isMerging ? undefined : onClose())}>
      <DialogContent size="xl">
        <DialogHeader>
          <DialogTitle>{t('recordMerge.title')}</DialogTitle>
          <DialogDescription>{t('recordMerge.description')}</DialogDescription>
        </DialogHeader>

        <div className="flex max-h-[70vh] flex-col gap-4 overflow-y-auto pr-1">
          {merge.preview.isPending ? (
            <div className="flex flex-col gap-2" role="status" aria-busy="true">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-32 w-full" />
            </div>
          ) : null}

          {merge.preview.isError ? (
            <div className="flex flex-col items-start gap-2" role="alert">
              <p className="text-sm text-destructive">{t('recordMerge.loadError')}</p>
              <Button type="button" variant="outline" size="sm" onClick={() => void merge.preview.refetch()}>
                {t('recordMerge.retry')}
              </Button>
            </div>
          ) : null}

          {data && merge.survivorId !== null ? (
            <>
              <MergeRecordsHeader
                records={data.records}
                survivorId={merge.survivorId}
                suggestedId={data.suggested_survivor_id}
                onSelect={merge.selectSurvivor}
              />
              <MergeFieldsTable
                records={data.records}
                fields={data.fields}
                survivorId={merge.survivorId}
                overrides={merge.overrides}
                showEqual={merge.showEqual}
                onShowEqualChange={merge.setShowEqual}
                onChoose={merge.chooseField}
              />
              <MergeCollections
                collections={data.collections}
                records={data.records}
                survivorId={merge.survivorId}
                discarded={merge.discarded}
                onToggle={merge.toggleItem}
              />
              <MergeLinked linked={data.linked} survivorId={merge.survivorId} />
            </>
          ) : null}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={merge.isMerging}>
            {t('recordMerge.cancel')}
          </Button>
          <Button type="button" variant="destructive" disabled={!data || merge.isMerging} onClick={() => setConfirmOpen(true)}>
            {merge.isMerging ? t('recordMerge.submitting') : t('recordMerge.submit')}
          </Button>
        </DialogFooter>

        <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('recordMerge.confirm.title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('recordMerge.confirm.description', {
                  names: merge.mergedRecords.map((record) => record.name).join(', '),
                  survivor: merge.survivor?.name ?? '',
                })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('recordMerge.cancel')}</AlertDialogCancel>
              <AlertDialogAction className={buttonVariants({ variant: 'destructive' })} onClick={() => merge.merge()}>
                {t('recordMerge.submit')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </DialogContent>
    </Dialog>
  )
}
