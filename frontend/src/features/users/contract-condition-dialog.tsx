import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CONTRACT_TYPES_FOR_SELECT_RESOURCE } from '@/features/contract-types/for-select-api'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { classifyContractError } from '@/features/users/contract-history-errors'
import {
  buildContractConditionSchema,
  toContractPayload,
  type ContractConditionFormValues,
} from '@/features/users/contract-history-schema'
import { COLLECTIVE_AGREEMENT_LEVELS_FOR_SELECT_RESOURCE } from '@/features/collective-agreements/for-select-api'
import { SALARY_PERIODS } from '@/features/users/contract-history-types'
import { useCreateEmploymentContract } from '@/features/users/use-contract-history'
import { RELATIONSHIP_TYPES, WORKING_TIMES, type WorkingTime } from '@/features/users/types'

/** Select value standing for "no working time": Radix items cannot carry an empty string. */
const NO_WORKING_TIME = 'none'

const DEFAULT_VALUES: ContractConditionFormValues = {
  starts_on: '',
  relationship_type: 'employee',
  contract_type_id: null,
  working_time: null,
  contract_expires_on: '',
  company_id: null,
  collective_agreement_level_id: null,
  salary_amount: '',
  salary_period: null,
  change_reason: '',
}

/** Request fields a 422 can name that this form shows. */
const SERVER_ERROR_FIELDS = [
  'starts_on',
  'relationship_type',
  'contract_type_id',
  'working_time',
  'contract_expires_on',
  'company_id',
  'collective_agreement_level_id',
  'salary_amount',
  'salary_period',
  'change_reason',
] as const

interface ContractConditionDialogProps {
  userId: number
  open: boolean
  onOpenChange: (open: boolean) => void
  canManageCompensation: boolean
}

/** "Nuova condizione" (spec 0272 D-1): appends a condition with its start date; conditions are never edited. */
export function ContractConditionDialog({ userId, open, onOpenChange, canManageCompensation }: ContractConditionDialogProps) {
  const { t } = useTranslation()
  const create = useCreateEmploymentContract(userId)
  const schema = useMemo(() => buildContractConditionSchema(t), [t])
  const form = useForm<ContractConditionFormValues>({ resolver: zodResolver(schema), defaultValues: DEFAULT_VALUES })

  const relationshipType = useWatch({ control: form.control, name: 'relationship_type' })

  const close = (next: boolean) => {
    if (!next) {
      form.reset(DEFAULT_VALUES)
    }
    onOpenChange(next)
  }

  const submit = async (values: ContractConditionFormValues) => {
    try {
      await create.mutateAsync(toContractPayload(values, canManageCompensation))
      toast.success(t('users.detail.employment.contractHistory.created'))
      close(false)
    } catch (error) {
      const { kind, fieldErrors } = classifyContractError(error)
      if (kind === 'validation') {
        let shown = false
        for (const field of SERVER_ERROR_FIELDS) {
          const message = fieldErrors[field]?.[0]
          if (message) {
            form.setError(field, { message })
            shown = true
          }
        }
        if (shown) {
          return
        }
      }
      toast.error(kind === 'forbidden' ? t('users.detail.employment.contractHistory.forbidden') : t('users.detail.employment.contractHistory.genericError'))
    }
  }

  const f = (key: string) => t(`users.detail.employment.contractHistory.form.${key}`)

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>{t('users.detail.employment.contractHistory.add')}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <div className="flex flex-col gap-3">
            <FormField
              control={form.control}
              name="starts_on"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{f('startsOn')}</FormLabel>
                  <FormControl>
                    <Input type="date" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="relationship_type"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{f('relationshipType')}</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {RELATIONSHIP_TYPES.map((type) => (
                        <SelectItem key={type} value={type}>
                          {t(`enums.relationship_type.${type}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <RelationSelectField
              control={form.control}
              name="contract_type_id"
              metaKey="employment_contract.contract_type_id"
              label={f('contractType')}
              resource={CONTRACT_TYPES_FOR_SELECT_RESOURCE}
              searchPlaceholder={f('contractTypeSearch')}
              selected={null}
              placeholder={f('contractTypePlaceholder')}
              emptyLabel={f('contractTypeEmpty')}
              errorLabel={f('contractTypeError')}
              clearLabel={t('common.clear')}
              retryLabel={t('common.retry')}
              required={relationshipType === 'employee'}
            />
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="working_time"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={relationshipType === 'employee'}>{f('workingTime')}</FormLabel>
                    <Select
                      value={field.value ?? NO_WORKING_TIME}
                      onValueChange={(value) => field.onChange(value === NO_WORKING_TIME ? null : (value as WorkingTime))}
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={f('workingTimePlaceholder')} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value={NO_WORKING_TIME}>{f('workingTimeNone')}</SelectItem>
                        {WORKING_TIMES.map((time) => (
                          <SelectItem key={time} value={time}>
                            {t(`enums.working_time.${time}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="contract_expires_on"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{f('contractExpiresOn')}</FormLabel>
                    <FormControl>
                      <Input type="date" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <RelationSelectField
              control={form.control}
              name="company_id"
              metaKey="employment_contract.company_id"
              label={f('company')}
              resource={COMPANIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={f('companySearch')}
              selected={null}
              placeholder={f('companyPlaceholder')}
              emptyLabel={f('companyEmpty')}
              errorLabel={f('companyError')}
              clearLabel={t('common.clear')}
              retryLabel={t('common.retry')}
            />
            <RelationSelectField
              control={form.control}
              name="collective_agreement_level_id"
              metaKey="employment_contract.collective_agreement_level_id"
              label={f('level')}
              resource={COLLECTIVE_AGREEMENT_LEVELS_FOR_SELECT_RESOURCE}
              searchPlaceholder={f('levelSearch')}
              selected={null}
              placeholder={f('levelPlaceholder')}
              emptyLabel={f('levelEmpty')}
              errorLabel={f('levelError')}
              clearLabel={t('common.clear')}
              retryLabel={t('common.retry')}
            />
            {canManageCompensation ? (
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="salary_amount"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{f('salaryAmount')}</FormLabel>
                      <FormControl>
                        <Input type="number" min="0" step="0.01" inputMode="decimal" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="salary_period"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{f('salaryPeriod')}</FormLabel>
                      <Select value={field.value ?? ''} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={f('salaryPeriodPlaceholder')} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {SALARY_PERIODS.map((period) => (
                            <SelectItem key={period} value={period}>
                              {t(`users.detail.employment.contractHistory.periods.${period}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            ) : null}
            <FormField
              control={form.control}
              name="change_reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{f('changeReason')}</FormLabel>
                  <FormControl>
                    <Input autoComplete="off" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        </Form>
        <DialogFooter>
          <Button type="button" variant="ghost" size="sm" onClick={() => close(false)} disabled={create.isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="button" size="sm" onClick={form.handleSubmit(submit)} disabled={create.isPending}>
            {f('save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
