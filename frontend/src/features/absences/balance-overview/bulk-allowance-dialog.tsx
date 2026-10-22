import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { AllowanceFormFields } from '@/features/absences/balances/allowance-form-fields'
import { buildAllowanceSchema, type AllowanceFormValues } from '@/features/absences/balances/allowance-schema'
import { useBulkAllowance } from '@/features/absences/balance-overview/use-bulk-allowance'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import type { AbsenceBalanceOverview } from '@/features/absences/types'

/** The bulk dialog is days only and never touches the carry-over (kept at a neutral value the schema accepts). */
const DEFAULT_VALUES: AllowanceFormValues = { use_default: false, unit: 'days', annual_value: '', carried_hours: '0' }

interface BulkAllowanceDialogProps {
  onOpenChange: (open: boolean) => void
  types: AbsenceBalanceOverview['types']
  year: number
  userIds: number[]
  onDone: () => void
}

/** "Imposta spettanza" (mounted only while open, so every opening starts clean): one allowance in days (or the type default) for every selected person. */
export function BulkAllowanceDialog({ onOpenChange, types, year, userIds, onDone }: BulkAllowanceDialogProps) {
  const { t } = useTranslation()
  const [typeId, setTypeId] = useState<number | null>(null)
  const form = useForm<AllowanceFormValues>({
    resolver: zodResolver(buildAllowanceSchema(t)),
    defaultValues: DEFAULT_VALUES,
  })
  const mutation = useBulkAllowance()
  const selectedTypeId = typeId ?? types[0]?.id ?? null

  function onSubmit(values: AllowanceFormValues) {
    if (selectedTypeId === null) {
      return
    }
    mutation.mutate(
      {
        absence_type_id: selectedTypeId,
        year,
        user_ids: userIds,
        annual_days: values.use_default ? null : Number(values.annual_value.trim().replace(',', '.')),
      },
      {
        onSuccess: ({ updated }) => {
          toast.success(t('absences.balances.overview.bulk.success', { count: updated }))
          onDone()
        },
        onError: (error) => toast.error(serverMessage(error) ?? t('absences.balances.overview.bulk.error')),
      },
    )
  }

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('absences.balances.overview.bulk.title')}</DialogTitle>
          <DialogDescription>
            {t('absences.balances.overview.bulk.description', { count: userIds.length, year })}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
            <SegmentedControl
              aria-label={t('absences.balances.overview.bulk.type')}
              value={selectedTypeId === null ? null : String(selectedTypeId)}
              options={types.map((type) => ({ value: String(type.id), label: type.name }))}
              onValueChange={(value) => setTypeId(Number(value))}
            />
            <AllowanceFormFields form={form} canUseDays isBulk />
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={mutation.isPending || selectedTypeId === null}>
                {t('absences.balances.form.save')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
