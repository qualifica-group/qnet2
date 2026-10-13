import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { usePurchaseRequestApprovers } from '@/features/purchase-requests/use-purchase-request-approvers'
import type { RequestRefs } from '@/features/purchase-requests/purchase-request-refs'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

interface ApproverFieldProps {
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  refs: RequestRefs
}

/** Function manager picker limited to the approvers allowed for the chosen requester (spec 0245 D-1/D-2). */
export function PurchaseRequestApproverField({ control, setValue, refs }: ApproverFieldProps) {
  const { t } = useTranslation()
  const { options, source, hasRequester, isError } = usePurchaseRequestApprovers({ control, setValue, refs })

  let description: string | undefined
  if (!hasRequester) {
    description = t('purchaseRequests.approver.requesterFirst')
  } else if (isError) {
    description = t('purchaseRequests.select.error')
  } else if (source === 'none') {
    description = t('purchaseRequests.approver.none')
  }

  return (
    <MetaField
      control={control}
      name="function_manager_id"
      metaKey="function_manager_id"
      label={t('purchaseRequests.fields.function_manager_id')}
      description={description === undefined ? undefined : <p className="text-xs text-muted-foreground">{description}</p>}
      required
    >
      {({ field, disabled }) => (
        <Select
          value={field.value === null ? '' : String(field.value)}
          onValueChange={(next) => field.onChange(Number(next))}
          disabled={disabled || !hasRequester}
        >
          <FormControl>
            <SelectTrigger className="h-8 w-full text-sm">
              <SelectValue placeholder={t('purchaseRequests.select.placeholder')} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((approver) => (
              <SelectItem key={approver.id} value={String(approver.id)}>
                {approver.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}
