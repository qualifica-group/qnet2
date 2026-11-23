import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Euro } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { CELL_WRAPPER } from '@/features/table/cell-renderers'
import type { ProformaStatus } from '@/features/proforma-requests/types'
import { cn } from '@/lib/utils'

/** The commessa a proforma request is raised for, from its grid row. */
export interface ProformaTarget {
  id: number
  code: string
}

interface WorkOrderProformaCellProps extends ICellRendererParams {
  /** Opens the "Richiesta emissione Proforma" dialog of the row's commessa. */
  onOpen: (target: ProformaTarget) => void
}

/** Icon tint per state, as the former row action: grey = no request, blue = pending, yellow = issued (spec 0193 D-5). */
const STATUS_CLASS: Record<ProformaStatus, string> = {
  none: 'text-muted-foreground',
  pending: 'text-blue-600 dark:text-blue-400',
  issued: 'text-yellow-600 dark:text-yellow-400',
}

const BUTTON_CLASS =
  'inline-flex size-6 items-center justify-center rounded-sm hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring'

function proformaStatusOf(value: unknown): ProformaStatus | null {
  return value === 'none' || value === 'pending' || value === 'issued' ? value : null
}

/**
 * Grid cell `proforma_status`, the "€" column (spec 0407, legacy `OrdersTable`):
 * the euro icon tinted by the proforma state, its label on hover. With no
 * request or a pending one it opens the request dialog; once issued it is inert.
 */
export function WorkOrderProformaCell({ value, data, onOpen }: WorkOrderProformaCellProps) {
  const { t } = useTranslation()
  const status = proformaStatusOf(value)
  const row = data as { id?: number; code?: string } | undefined
  if (status === null || row?.id === undefined) {
    return null
  }
  const label = t(`proformaRequests.cell.${status}`)
  const icon = <Euro className={cn('size-4', STATUS_CLASS[status])} aria-hidden="true" />

  return (
    <div className={CELL_WRAPPER}>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            {status === 'issued' ? (
              <span role="img" tabIndex={0} aria-label={label} className={BUTTON_CLASS}>
                {icon}
              </span>
            ) : (
              <button
                type="button"
                aria-label={label}
                onClick={() => onOpen({ id: Number(row.id), code: String(row.code ?? '') })}
                className={cn(BUTTON_CLASS, 'cursor-pointer')}
              >
                {icon}
              </button>
            )}
          </TooltipTrigger>
          <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </div>
  )
}
