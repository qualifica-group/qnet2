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
import { Form } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { buildAllowanceSchema, type AllowanceFormValues } from '@/features/absences/balances/allowance-schema'
import { AllowanceFormFields } from '@/features/absences/balances/allowance-form-fields'
import {
  daysTextToMinutes,
  hoursTextToMinutes,
  minutesToDaysText,
  minutesToHoursText,
} from '@/features/absences/balances/balance-format'
import { useSaveAllowance } from '@/features/absences/balances/use-save-allowance'
import type { AbsenceBalanceRow } from '@/features/absences/types'

/** Server field names mapped onto the hour-based form fields. */
const SERVER_FIELD_MAP = { annual_minutes: 'annual_value', carried_over_minutes: 'carried_hours' } as const

interface AllowanceDialogProps {
  row: AbsenceBalanceRow | null
  userId: number
  year: number
  /** Working-day length of the person: days convert through it. */
  dailyMinutes: number
  onOpenChange: (open: boolean) => void
}

function toFormValues(row: AbsenceBalanceRow | null, dailyMinutes: number): AllowanceFormValues {
  const annualMinutes = row?.annual_minutes ?? 0
  const inDays = dailyMinutes > 0

  return {
    use_default: row?.source === 'default',
    unit: inDays ? 'days' : 'hours',
    annual_value: inDays ? minutesToDaysText(annualMinutes, dailyMinutes) : minutesToHoursText(annualMinutes),
    carried_hours: minutesToHoursText(row?.carried_over_minutes ?? 0),
  }
}

function toAnnualMinutes(values: AllowanceFormValues, dailyMinutes: number): number | null {
  if (values.use_default) {
    return null
  }
  return values.unit === 'days'
    ? daysTextToMinutes(values.annual_value, dailyMinutes)
    : hoursTextToMinutes(values.annual_value)
}

/** "Modifica spettanza": annual allowance (days or hours, or the type default) and carry-over of one person/type/year. */
export function AllowanceDialog({ row, userId, year, dailyMinutes, onOpenChange }: AllowanceDialogProps) {
  const { t } = useTranslation()
  const form = useForm<AllowanceFormValues>({
    resolver: zodResolver(buildAllowanceSchema(t)),
    defaultValues: toFormValues(row, dailyMinutes),
  })
  const mutation = useSaveAllowance()

  useEffect(() => {
    form.reset(toFormValues(row, dailyMinutes))
  }, [row, dailyMinutes, form])

  function onSubmit(values: AllowanceFormValues) {
    if (!row) {
      return
    }
    mutation.mutate(
      {
        user_id: userId,
        absence_type_id: row.absence_type.id,
        year,
        annual_minutes: toAnnualMinutes(values, dailyMinutes),
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
                form.setError(formField, { message })
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
            <AllowanceFormFields form={form} canUseDays={dailyMinutes > 0} />
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
