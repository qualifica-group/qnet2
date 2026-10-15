import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { cn } from '@/lib/utils'
import { FormField, FormItem, FormMessage } from '@/components/ui/form'
import {
  CREATE_SUPPLIER_CHOICE,
  type ApprovalFormValues,
  type ApprovalSchemaOutput,
} from '@/features/commission-settlements/settlement-approval-schema'
import type { ApprovalDraft } from '@/features/commission-settlements/types'

interface RadioOptionProps {
  checked: boolean
  label: string
  detail?: string
  disabled?: boolean
  hint?: string
  onSelect: () => void
}

function RadioOption({ checked, label, detail, disabled = false, hint, onSelect }: RadioOptionProps) {
  return (
    <label
      className={cn(
        'flex items-center gap-2 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs',
        disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
      )}
    >
      <input type="radio" name="supplier_choice" checked={checked} disabled={disabled} onChange={onSelect} className="size-3.5 accent-primary" />
      <span className="font-medium">{label}</span>
      {detail ? <span className="text-muted-foreground">{detail}</span> : null}
      {hint ? <span className="text-muted-foreground italic">{hint}</span> : null}
    </label>
  )
}

interface SettlementApprovalSupplierProps {
  draft: ApprovalDraft
  control: Control<ApprovalFormValues, unknown, ApprovalSchemaOutput>
}

/**
 * Supplier registry the passive invoice is addressed to: the one already linked
 * to the agent (read-only), else a candidate with the same tax code or VAT
 * number, else a new registry created from the referent (needs `registries.create`).
 */
export function SettlementApprovalSupplier({ draft, control }: SettlementApprovalSupplierProps) {
  const { t } = useTranslation()
  const legend = t('commissionSettlements.approvalDialog.supplier')

  if (draft.supplier !== null) {
    return (
      <div className="grid gap-1">
        <span className="text-xs font-medium">{legend}</span>
        <p className="text-sm">{draft.supplier.name}</p>
      </div>
    )
  }

  const hasOptions = draft.supplier_candidates.length > 0 || draft.can_create_supplier

  return (
    <FormField
      control={control}
      name="supplier_choice"
      render={({ field }) => (
        <FormItem className="gap-1">
          <fieldset className="grid gap-1.5">
            <legend className="mb-1 text-xs font-medium">{legend}</legend>
            {draft.supplier_candidates.map((candidate) => (
              <RadioOption
                key={candidate.id}
                checked={field.value === candidate.id}
                label={candidate.name}
                detail={[candidate.tax_code, candidate.vat_number].filter(Boolean).join(' - ')}
                disabled={!candidate.is_supplier}
                hint={candidate.is_supplier ? undefined : t('commissionSettlements.approvalDialog.notSupplier')}
                onSelect={() => field.onChange(candidate.id)}
              />
            ))}
            {draft.can_create_supplier ? (
              <RadioOption
                checked={field.value === CREATE_SUPPLIER_CHOICE}
                label={t('commissionSettlements.approvalDialog.createSupplier')}
                onSelect={() => field.onChange(CREATE_SUPPLIER_CHOICE)}
              />
            ) : null}
            {hasOptions ? null : (
              <p className="text-xs text-muted-foreground">{t('commissionSettlements.approvalDialog.noSupplierOptions')}</p>
            )}
          </fieldset>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
