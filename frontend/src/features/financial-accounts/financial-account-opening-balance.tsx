import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Can } from '@/features/auth/can'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { updateOpeningBalance } from '@/features/financial-accounts/api'
import {
  openingBalanceSchema,
  type OpeningBalanceFormInput,
  type OpeningBalanceFormValues,
} from '@/features/financial-accounts/opening-balance-schema'
import { formatEuro } from '@/features/invoices/invoice-format'

const MANAGE_PERMISSION = 'financial-accounts.manageOpeningBalance'

function formatDate(iso: string): string {
  const [year, month, day] = iso.split('-')
  return `${day}/${month}/${year}`
}

interface OpeningBalanceFormProps {
  accountId: number
  defaults: OpeningBalanceFormInput
  onDone: () => void
}

function OpeningBalanceForm({ accountId, defaults, onDone }: OpeningBalanceFormProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const form = useForm<OpeningBalanceFormInput, unknown, OpeningBalanceFormValues>({
    resolver: zodResolver(openingBalanceSchema),
    defaultValues: defaults,
  })
  const { errors } = form.formState

  const save = useMutation({
    mutationFn: (values: OpeningBalanceFormValues) => updateOpeningBalance(accountId, values),
    onSuccess: () => {
      toast.success(t('accountStatements.openingBalance.saved'))
      void queryClient.invalidateQueries({ queryKey: ['financial-accounts'] })
      void queryClient.invalidateQueries({ queryKey: ['account-statements'] })
      onDone()
    },
    onError: (error) => {
      const handled = applyServerValidationErrors(error, form.setError, ['opening_balance', 'opening_balance_date'])
      if (!handled) toast.error(t('accountStatements.openingBalance.saveError'))
    },
  })

  const amountError = errors.opening_balance?.message
  const dateError = errors.opening_balance_date?.message

  return (
    <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="flex flex-col gap-3" noValidate>
      <div className="flex flex-col gap-1">
        <Label htmlFor="opening-balance-amount" className="text-xs">
          {t('accountStatements.openingBalance.amount')}
        </Label>
        <Input
          id="opening-balance-amount"
          inputMode="decimal"
          aria-invalid={!!amountError}
          aria-describedby={amountError ? 'opening-balance-amount-error' : undefined}
          {...form.register('opening_balance')}
        />
        {amountError ? (
          <span id="opening-balance-amount-error" role="alert" className="text-xs text-destructive">
            {t(`accountStatements.openingBalance.${amountError}`, { defaultValue: amountError })}
          </span>
        ) : null}
      </div>
      <div className="flex flex-col gap-1">
        <Label htmlFor="opening-balance-date" className="text-xs">
          {t('accountStatements.openingBalance.date')}
        </Label>
        <Input
          id="opening-balance-date"
          type="date"
          aria-invalid={!!dateError}
          aria-describedby={dateError ? 'opening-balance-date-error' : undefined}
          {...form.register('opening_balance_date')}
        />
        {dateError ? (
          <span id="opening-balance-date-error" role="alert" className="text-xs text-destructive">
            {t(`accountStatements.openingBalance.${dateError}`, { defaultValue: dateError })}
          </span>
        ) : null}
      </div>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="secondary" size="sm" onClick={onDone}>
          {t('accountStatements.openingBalance.cancel')}
        </Button>
        <Button type="submit" size="sm" disabled={save.isPending}>
          {t('accountStatements.openingBalance.save')}
        </Button>
      </div>
    </form>
  )
}

interface FinancialAccountOpeningBalanceProps {
  accountId: number
  openingBalance: string
  openingBalanceDate: string
  /** Null/undefined hides the current balance (no `treasury-movements.view`). */
  currentBalance?: string | null
}

/** Opening balance (amount + date) with a gated editor, and the current balance linking to the statement. */
export function FinancialAccountOpeningBalance({
  accountId,
  openingBalance,
  openingBalanceDate,
  currentBalance = null,
}: FinancialAccountOpeningBalanceProps) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState(false)

  return (
    <div className="flex flex-col gap-2">
      <dl className="grid grid-cols-2 gap-3 text-sm">
        <div>
          <dt className="text-xs text-muted-foreground">{t('accountStatements.openingBalance.amount')}</dt>
          <dd className="font-medium tabular-nums">{formatEuro(openingBalance)}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted-foreground">{t('accountStatements.openingBalance.date')}</dt>
          <dd className="font-medium tabular-nums">{formatDate(openingBalanceDate)}</dd>
        </div>
        {currentBalance !== null ? (
          <div className="col-span-2">
            <dt className="text-xs text-muted-foreground">{t('accountStatements.openingBalance.currentBalance')}</dt>
            <dd className="flex flex-wrap items-baseline gap-x-3 font-medium tabular-nums">
              {formatEuro(currentBalance)}
              <Link
                to={`/account-statements?account=${accountId}`}
                className="text-xs font-normal text-primary underline"
              >
                {t('accountStatements.openingBalance.viewStatement')}
              </Link>
            </dd>
          </div>
        ) : null}
      </dl>
      <Can permission={MANAGE_PERMISSION}>
        <div>
          <Button type="button" variant="secondary" size="sm" onClick={() => setEditing(true)}>
            {t('accountStatements.openingBalance.edit')}
          </Button>
        </div>
        <Dialog open={editing} onOpenChange={setEditing}>
          <DialogContent size="sm">
            <DialogHeader className="text-left">
              <DialogTitle className="text-base">{t('accountStatements.openingBalance.editTitle')}</DialogTitle>
              <DialogDescription className="sr-only">{t('accountStatements.openingBalance.title')}</DialogDescription>
            </DialogHeader>
            <OpeningBalanceForm
              accountId={accountId}
              defaults={{ opening_balance: openingBalance, opening_balance_date: openingBalanceDate }}
              onDone={() => setEditing(false)}
            />
          </DialogContent>
        </Dialog>
      </Can>
    </div>
  )
}
