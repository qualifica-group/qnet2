import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { ForSelectItem } from '@/features/for-select/types'
import { TreasuryPicker } from '@/features/treasury-movements/treasury-movement-pickers'
import { useOriginCompany } from '@/features/treasury-movements/use-origin-company'
import { TreasurySubAccountField } from '@/features/treasury-movements/treasury-movement-sub-account-field'
import { parseSubAccountValue, type TreasuryMovementFormValues } from '@/features/treasury-movements/treasury-movement-schema'
import {
  SUB_ACCOUNT_KINDS_BY_TYPE,
  TREASURY_DIRECTIONS,
  TREASURY_MOVEMENT_TYPES,
  type TreasuryMovement,
  type TreasuryMovementType,
} from '@/features/treasury-movements/types'

function toItem(ref: { id: number; name?: string; code?: string } | null | undefined): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name ?? ref.code ?? String(ref.id) } : null
}

function TypeField() {
  const { t } = useTranslation()
  const { control, getValues, setValue } = useFormContext<TreasuryMovementFormValues>()

  /** Changing type clears what no longer applies: direction, destination and a sub-account the type forbids. */
  function changeType(next: TreasuryMovementType) {
    setValue('direction', '')
    setValue('counterpart_account_id', null)
    const current = parseSubAccountValue(getValues('sub_account'))
    if (current === null || !SUB_ACCOUNT_KINDS_BY_TYPE[next].includes(current.kind)) {
      setValue('sub_account', '')
    }
  }

  return (
    <FormField
      control={control}
      name="type"
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{t('treasuryMovements.form.type')}</FormLabel>
          <Select
            value={field.value}
            onValueChange={(next) => {
              field.onChange(next)
              changeType(next as TreasuryMovementType)
            }}
          >
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {TREASURY_MOVEMENT_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`treasuryMovements.type.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

function DirectionField() {
  const { t } = useTranslation()
  const { control } = useFormContext<TreasuryMovementFormValues>()

  return (
    <FormField
      control={control}
      name="direction"
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{t('treasuryMovements.form.direction')}</FormLabel>
          <Select value={field.value} onValueChange={field.onChange}>
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t('treasuryMovements.form.pick')} />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {TREASURY_DIRECTIONS.map((direction) => (
                <SelectItem key={direction} value={direction}>
                  {t(`treasuryMovements.direction.${direction}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

interface TextFieldProps {
  name: 'movement_date' | 'amount'
  label: string
  type?: string
  inputMode?: 'decimal'
}

function TextField({ name, label, type, inputMode }: TextFieldProps) {
  const { control } = useFormContext<TreasuryMovementFormValues>()

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{label}</FormLabel>
          <FormControl>
            <Input {...field} type={type} inputMode={inputMode} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

function DescriptionField() {
  const { t } = useTranslation()
  const { control } = useFormContext<TreasuryMovementFormValues>()

  return (
    <FormField
      control={control}
      name="description"
      render={({ field }) => (
        <FormItem className="sm:col-span-2">
          <FormLabel required>{t('treasuryMovements.form.description')}</FormLabel>
          <FormControl>
            <Textarea {...field} rows={2} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

/** Transfer destination: same company as the origin (D-6), never the origin itself. */
function CounterpartPicker({ selectedItem }: { selectedItem: ForSelectItem | null }) {
  const { control } = useFormContext<TreasuryMovementFormValues>()
  const originId = useWatch({ control, name: 'financial_account_id' })
  const { companyId, isLoading } = useOriginCompany(originId)

  return (
    <TreasuryPicker
      name="counterpart_account_id"
      required
      disabled={originId === null || isLoading}
      selectedItem={selectedItem}
      params={companyId === null ? undefined : { company_id: companyId }}
      isItemDisabled={(item) => item.id === originId}
    />
  )
}

interface TreasuryMovementFieldsProps {
  /** The saved movement when editing (hydrates the pickers and keeps its sub-account selectable). */
  movement: TreasuryMovement | null
}

/** Fields of the movement form: the ones that depend on the type appear only for it. */
export function TreasuryMovementFields({ movement }: TreasuryMovementFieldsProps) {
  const { t } = useTranslation()
  const { control, setValue } = useFormContext<TreasuryMovementFormValues>()
  const type = useWatch({ control, name: 'type' })

  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <TypeField />
      {type === 'adjustment' ? <DirectionField /> : null}
      <TextField name="movement_date" label={t('treasuryMovements.form.date')} type="date" />
      <TextField name="amount" label={t('treasuryMovements.form.amount')} inputMode="decimal" />
      <TreasuryPicker
        name="financial_account_id"
        required
        selectedItem={toItem(movement?.account)}
        onPicked={() => setValue('counterpart_account_id', null)}
      />
      {type === 'transfer' ? <CounterpartPicker selectedItem={toItem(movement?.counterpart_account)} /> : null}
      <TreasurySubAccountField saved={movement?.sub_account ?? null} />
      <DescriptionField />
      <TreasuryPicker name="registry_id" selectedItem={toItem(movement?.registry)} />
      <TreasuryPicker name="work_order_id" selectedItem={toItem(movement?.work_order)} />
    </div>
  )
}
