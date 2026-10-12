import type { ComponentProps, ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Calculator, ListChecks, MessageSquareText, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Textarea } from '@/components/ui/textarea'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { LineDocumentsButton } from '@/features/purchase-requests/purchase-request-documents'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

const REASON_MAX = 5000

type Control_ = Control<PurchaseRequestFormValues>

interface IconActionProps extends Omit<ComponentProps<typeof Button>, 'children' | 'size' | 'aria-label'> {
  label: string
  icon: ReactNode
}

/** Icon-only action: the label is its accessible name and its tooltip (spec 0209 D-11). */
function IconAction({ label, icon, className, ...props }: IconActionProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button type="button" variant="outline" size="icon-xs" className={cn('bg-card', className)} aria-label={label} {...props}>
          {icon}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

interface ReasonPopoverProps {
  control: Control_
  index: number
  label: string
  /** The reason text is read-only on a locked line. */
  readOnly: boolean
  /** Highlights the trigger when a reason is written. */
  filled: boolean
}

/** The line reason lives behind an icon that opens a popover with the textarea (spec 0209 D-10). */
function ReasonPopover({ control, index, label, readOnly, filled }: ReasonPopoverProps) {
  return (
    <Popover>
      <Tooltip>
        <TooltipTrigger asChild>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant={filled ? 'default' : 'outline'}
              size="icon-xs"
              className={filled ? undefined : 'bg-card'}
              aria-label={label}
            >
              <MessageSquareText />
            </Button>
          </PopoverTrigger>
        </TooltipTrigger>
        <TooltipContent>{label}</TooltipContent>
      </Tooltip>
      <PopoverContent align="end" className="w-80 p-2">
        <FormField
          control={control}
          name={`lines.${index}.reason`}
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormControl>
                <Textarea className="min-h-20 text-xs" aria-label={label} maxLength={REASON_MAX} readOnly={readOnly} {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      </PopoverContent>
    </Popover>
  )
}

interface LineActionsProps {
  control: Control_
  index: number
  lineId?: number
  locked: boolean
  hasReason: boolean
  pendingFiles: number
  showChangeStatus: boolean
  showRemove: boolean
  onChangeStatus: () => void
  onExtractVat: () => void
  onRemove: () => void
}

/** The five icon-only actions of a line: reason, change status, extract VAT, documents, remove. */
export function LineActions({
  control,
  index,
  lineId,
  locked,
  hasReason,
  pendingFiles,
  showChangeStatus,
  showRemove,
  onChangeStatus,
  onExtractVat,
  onRemove,
}: LineActionsProps) {
  const { t } = useTranslation()
  const n = index + 1
  return (
    <div className="flex items-center justify-end gap-1">
      <ReasonPopover
        control={control}
        index={index}
        label={`${t('purchaseRequests.lines.reason')} ${n}`}
        readOnly={locked}
        filled={hasReason}
      />
      {showChangeStatus ? (
        <IconAction label={t('purchaseRequests.lines.changeStatus')} icon={<ListChecks />} onClick={onChangeStatus} />
      ) : null}
      {locked ? null : (
        <IconAction
          label={`${t('purchaseRequests.lines.extractVat')} ${n}`}
          icon={<Calculator />}
          onClick={onExtractVat}
        />
      )}
      <LineDocumentsButton control={control} index={index} lineId={lineId} pendingCount={pendingFiles} />
      {showRemove ? (
        <IconAction
          label={`${t('purchaseRequests.lines.remove')} ${n}`}
          icon={<Trash2 />}
          className="text-destructive"
          onClick={onRemove}
        />
      ) : null}
    </div>
  )
}
