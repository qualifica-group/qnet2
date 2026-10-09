import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useAbilities } from '@/features/auth/use-abilities'
import { ApiClientFormBody } from '@/features/api-integrations/components/api-client-form-body'
import type { ApiKeyReveal } from '@/features/api-integrations/components/api-key-dialog'
import { useApiClient } from '@/features/api-integrations/use-api-clients'

export type ApiClientFormTarget = { type: 'create' } | { type: 'edit'; id: number }

interface ApiClientFormDialogProps {
  target: ApiClientFormTarget
  onClose: () => void
  onKeyIssued: (reveal: ApiKeyReveal) => void
  onSaved: () => void
  onRotate: (id: number, name: string) => void
  onRevoke: (id: number, name: string) => void
}

/** Loads the fresh client when editing before mounting the form. */
export function ApiClientFormDialog({
  target,
  onClose,
  onKeyIssued,
  onSaved,
  onRotate,
  onRevoke,
}: ApiClientFormDialogProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const clientQuery = useApiClient(target.type === 'edit' ? target.id : null)
  const isEdit = target.type === 'edit'

  const hasError = isEdit && clientQuery.isError
  const ready = !isEdit || clientQuery.data
  const client = clientQuery.data

  return (
    <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle>{t(isEdit ? 'apiIntegrations.clients.editTitle' : 'apiIntegrations.clients.newTitle')}</DialogTitle>
          <DialogDescription>{t('apiIntegrations.clients.description')}</DialogDescription>
        </DialogHeader>
        {hasError ? (
          <p role="alert" className="text-sm text-destructive">
            {t('apiIntegrations.clients.loadError')}
          </p>
        ) : !ready ? (
          <div className="flex flex-col gap-3" aria-busy="true">
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : (
          <ApiClientFormBody
            key={client?.updated_at ?? 'new'}
            client={client ?? null}
            readOnly={isEdit && !can('api-clients.update')}
            onKeyIssued={onKeyIssued}
            onSaved={onSaved}
            onCancel={onClose}
            onRotate={() => client && onRotate(client.id, client.name)}
            onRevoke={() => client && onRevoke(client.id, client.name)}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}
