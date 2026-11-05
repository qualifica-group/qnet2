import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { useAbilities } from '@/features/auth/use-abilities'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { fetchTreasuryMovement } from '@/features/treasury-movements/api'
import { treasuryMovementKeys } from '@/features/treasury-movements/query-keys'
import { TreasuryMovementFields } from '@/features/treasury-movements/treasury-movement-fields'
import {
  emptyTreasuryMovementValues,
  treasuryMovementToValues,
} from '@/features/treasury-movements/treasury-movement-payload'
import { useTreasuryMovementForm } from '@/features/treasury-movements/use-treasury-movement-form'
import { TREASURY_MOVEMENT_ATTACHABLE_TYPE, type TreasuryMovement } from '@/features/treasury-movements/types'

const FORM_ID = 'treasury-movement-form'

interface MovementFormProps {
  /** The movement being edited; null creates. */
  movement: TreasuryMovement | null
  onSaved: () => void
  onClose: () => void
}

function MovementForm({ movement, onSaved, onClose }: MovementFormProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { form, isSubmitting, submit } = useTreasuryMovementForm({
    movementId: movement?.id ?? null,
    defaultValues: movement ? treasuryMovementToValues(movement) : emptyTreasuryMovementValues(),
    onSaved,
  })

  return (
    <>
      <div className="flex max-h-[70vh] flex-col gap-4 overflow-y-auto px-0.5">
        <Form {...form}>
          <form id={FORM_ID} onSubmit={(event) => void submit(event)} noValidate>
            <TreasuryMovementFields movement={movement} />
          </form>
        </Form>
        {movement ? (
          <DocumentsSection
            resource={TREASURY_MOVEMENT_ATTACHABLE_TYPE}
            id={movement.id}
            canUpload={can('treasury-movements.update')}
            canDelete={can('treasury-movements.update')}
          />
        ) : null}
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
          {t('treasuryMovements.form.cancel')}
        </Button>
        <Button type="submit" form={FORM_ID} disabled={isSubmitting}>
          {isSubmitting ? t('treasuryMovements.form.saving') : t('treasuryMovements.form.save')}
        </Button>
      </DialogFooter>
    </>
  )
}

function EditLoader({ id, onSaved, onClose }: { id: number; onSaved: () => void; onClose: () => void }) {
  const { t } = useTranslation()
  const { data, isError, refetch } = useQuery({
    queryKey: treasuryMovementKeys.detail(id),
    queryFn: () => fetchTreasuryMovement(id),
  })

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <p className="text-sm text-destructive" role="alert">
          {t('treasuryMovements.form.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!data) {
    return <Skeleton className="h-40 w-full" />
  }
  return <MovementForm movement={data} onSaved={onSaved} onClose={onClose} />
}

interface TreasuryMovementDialogProps {
  /** `null` = closed; `{ id: null }` = create; `{ id }` = edit. */
  target: { id: number | null } | null
  onClose: () => void
  onSaved: () => void
}

/** Modal create/edit form of a treasury movement. */
export function TreasuryMovementDialog({ target, onClose, onSaved }: TreasuryMovementDialogProps) {
  const { t } = useTranslation()
  const editing = target?.id != null

  return (
    <Dialog open={target !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="lg">
        <DialogHeader>
          <DialogTitle>{t(editing ? 'treasuryMovements.form.editTitle' : 'treasuryMovements.form.createTitle')}</DialogTitle>
          <DialogDescription>{t('treasuryMovements.form.hint')}</DialogDescription>
        </DialogHeader>
        {target === null ? null : target.id === null ? (
          <MovementForm movement={null} onSaved={onSaved} onClose={onClose} />
        ) : (
          <EditLoader key={target.id} id={target.id} onSaved={onSaved} onClose={onClose} />
        )}
      </DialogContent>
    </Dialog>
  )
}
