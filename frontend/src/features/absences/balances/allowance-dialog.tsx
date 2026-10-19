import { useEffect } from 'react'
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
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { buildAllowanceSchema, type AllowanceFormValues } from '@/features/absences/balances/allowance-schema'
import { hoursTextToMinutes, minutesToHoursText } from '@/features/absences/balances/balance-format'
import { useSaveAllowance } from '@/features/absences/balances/use-save-allowance'
import type { AbsenceBalanceRow } from '@/features/absences/types'

/** Server field names mapped onto the hour-based form fields. */
const SERVER_FIELD_MAP = { annual_minutes: 'annual_hours', carried_over_minutes: 'carried_hours' } as const

interface AllowanceDialogProps {
  row: AbsenceBalanceRow | null
  userId: number
  year: number
  onOpenChange: (open: boolean) => void
}

function toFormValues(row: AbsenceBalanceRow | null): AllowanceFormValues {
  return {
    annual_hours: minutesToHoursText(row?.annual_minutes ?? 0),
    carried_hours: minutesToHoursText(row?.carried_over_minutes ?? 0),
  }
}

/** "Modifica spettanza": annual allowance and carry-over of one person/type/year, typed in decimal hours. */
export function AllowanceDialog({ row, userId, year, onOpenChange }: AllowanceDialogProps) {
  const { t } = useTranslation()
  const form = useForm<AllowanceFormValues>({
    resolver: zodResolver(buildAllowanceSchema(t)),
    defaultValues: toFormValues(row),
  })
  const mutation = useSaveAllowance()

  useEffect(() => {
    form.reset(toFormValues(row))
  }, [row, form])

  function onSubmit(values: AllowanceFormValues) {
    if (!row) {
      return
    }
    mutation.mutate(
      {
        user_id: userId,
        absence_type_id: row.absence_type.id,
        year,
        annual_minutes: hoursTextToMinutes(values.annual_hours),
        carried_over_minutes: hoursTextToMinutes(values.carried_hours),
      },
      {
        onSuccess: () => {
          toast.success(t('absences.balances.form.saved'))
          onOpenChange(false)
        },
        onError: (error) => {
          const mapped = applyServerValidationErrors(error, form.setError, [])
          if (mapped) {
            const errors = error.response?.data as { errors?: Record<string, string[]> } | undefined
            for (const [serverField, formField] of Object.entries(SERVER_FIELD_MAP)) {
              const message = errors?.errors?.[serverField]?.[0]
              if (message) {
                form.setError(formField as keyof AllowanceFormValues, { message })
              }
            }
          } else {
            toast.error(t('absences.balances.form.error'))
          }
        },
      },
    )
  }

  return (
    <Dialog open={row !== null} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('absences.balances.form.title', { type: row?.absence_type.name ?? '' })}</DialogTitle>
          <DialogDescription>{t('absences.balances.form.description', { year })}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
            <FormField
              control={form.control}
              name="annual_hours"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('absences.balances.form.annual')}</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" autoComplete="off" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="carried_hours"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('absences.balances.form.carried')}</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" autoComplete="off" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={mutation.isPending}>
                {t('absences.balances.form.save')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
