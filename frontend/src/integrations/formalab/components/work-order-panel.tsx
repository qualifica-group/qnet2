import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ClassCard } from '@/integrations/formalab/components/class-card'
import { EditionPicker } from '@/integrations/formalab/components/edition-picker'
import {
  useFormalabLinkMutations,
  useFormalabWorkOrderLink,
} from '@/integrations/formalab/use-formalab-work-order-link'

/** Body of the work order's "Formalab" tab: the linked class, or the picker to link one. */
export function FormalabWorkOrderPanel({ workOrderId }: { workOrderId: number }) {
  const { t } = useTranslation('formalab')
  const confirm = useConfirm()
  const { data, isLoading, isError } = useFormalabWorkOrderLink(workOrderId, true)
  const { link, sync, unlink } = useFormalabLinkMutations(workOrderId)
  const [changing, setChanging] = useState(false)

  if (isLoading) {
    return <Skeleton className="m-3 h-24" aria-busy="true" />
  }
  if (isError || !data) {
    return (
      <p role="alert" className="p-3 text-sm text-destructive">
        {t('workOrder.loadError')}
      </p>
    )
  }

  const handleUnlink = async () => {
    const confirmed = await confirm({
      title: t('workOrder.unlinkConfirmTitle'),
      description: t('workOrder.unlinkConfirmDescription'),
      confirmLabel: t('workOrder.unlink'),
      tone: 'destructive',
    })
    if (confirmed) {
      unlink.mutate()
    }
  }

  const handleLink = (editionId: number) => link.mutate(editionId, { onSuccess: () => setChanging(false) })

  return (
    <div className="flex flex-col gap-3 p-3">
      {data.link ? (
        <>
          <ClassCard link={data.link} />
          {data.can_manage ? (
            changing ? (
              <EditionPicker
                submitLabel={t('workOrder.change')}
                pending={link.isPending}
                onSubmit={handleLink}
                onCancel={() => setChanging(false)}
              />
            ) : (
              <div className="flex flex-wrap gap-2">
                <Button size="sm" disabled={sync.isPending} onClick={() => sync.mutate()}>
                  <RefreshCw className={sync.isPending ? 'size-3.5 animate-spin' : 'size-3.5'} aria-hidden="true" />
                  {t('workOrder.sync')}
                </Button>
                <Button size="sm" variant="secondary" onClick={() => setChanging(true)}>
                  {t('workOrder.changeEdition')}
                </Button>
                <Button size="sm" variant="secondary" disabled={unlink.isPending} onClick={() => void handleUnlink()}>
                  {t('workOrder.unlink')}
                </Button>
              </div>
            )
          ) : null}
        </>
      ) : data.can_manage ? (
        <>
          <p className="text-sm text-muted-foreground">{t('workOrder.notLinked')}</p>
          <EditionPicker submitLabel={t('workOrder.link')} pending={link.isPending} onSubmit={handleLink} />
        </>
      ) : (
        <p className="text-sm text-muted-foreground">{t('workOrder.notLinked')}</p>
      )}
    </div>
  )
}
