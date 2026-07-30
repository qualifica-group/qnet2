import { useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { AlertTriangle } from 'lucide-react'
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
import type { TableRow } from '@/features/table/types'
import { useConvertLeads } from '@/features/leads/use-convert-leads'
import type { LeadConversionBlocker, LeadConversionBlockedError } from '@/features/leads/types'

/** The lifecycle status a lead already holding an Opportunity is projected with. */
const CONVERTED_STATUS = 'converted_to_opportunity'

/** Discriminator of the 422 body a refused batch answers with (spec 0071). */
const NOT_CONVERTIBLE = 'not_convertible'

export interface ConvertLeadsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The selected rows: both the summary and the blocker labels are read off them. */
  rows: TableRow[]
  /** Ran after the batch converted, with how many Opportunities were created. */
  onConverted: (converted: number) => void
}

/** The row's Anagrafica name, or its id when the relation is empty. */
function leadLabel(row: TableRow | undefined, id: number): string {
  const registry = row?.registry as { id: number; name: string } | null | undefined

  return registry?.name ?? `#${id}`
}

/**
 * Confirm popup of the leads table's mass conversion (spec 0071).
 *
 * Two things it must make explicit, because they differ from the single row
 * action: the conversion is automatic (no Opportunity form to fill — N forms
 * are not composable), and the batch is all-or-nothing.
 *
 * The already-converted leads are caught client-side off the grid's own
 * `lead_status`, so the frequent case is fixed before submitting rather than
 * bouncing off a 422. Rows are NOT made unselectable for it: the same
 * selection still feeds bulk delete, which must keep reaching them. Everything
 * the client cannot know (a campaign deriving no product line) comes back as
 * the server's blocker list and is rendered inline.
 */
export function ConvertLeadsDialog({
  open,
  onOpenChange,
  rows,
  onConverted,
}: ConvertLeadsDialogProps) {
  const { t } = useTranslation()
  const [blockers, setBlockers] = useState<LeadConversionBlocker[] | null>(null)

  const convertMutation = useConvertLeads({
    onSuccess: (result) => {
      onConverted(result.converted)
      onOpenChange(false)
    },
  })

  const alreadyConverted = useMemo(
    () => rows.filter((row) => row.lead_status === CONVERTED_STATUS),
    [rows],
  )

  const handleOpenChange = useCallback(
    (next: boolean) => {
      if (!next) {
        setBlockers(null)
      }
      onOpenChange(next)
    },
    [onOpenChange],
  )

  const handleConfirm = useCallback(async () => {
    setBlockers(null)

    try {
      await convertMutation.mutateAsync({ lead_ids: rows.map((row) => row.id) })
    } catch (error) {
      const blocked = axios.isAxiosError(error)
        ? (error.response?.data?.errors as LeadConversionBlockedError | undefined)
        : undefined

      if (blocked?.reason === NOT_CONVERTIBLE) {
        setBlockers(blocked.blockers)
        return
      }

      toast.error(t('leads.bulkConvert.errors.generic'))
    }
  }, [convertMutation, rows, t])

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('leads.bulkConvert.title')}</DialogTitle>
          <DialogDescription>
            {t('leads.bulkConvert.description', { count: rows.length })}
          </DialogDescription>
        </DialogHeader>

        <p className="text-xs text-muted-foreground">{t('leads.bulkConvert.note')}</p>

        {alreadyConverted.length > 0 ? (
          <div
            role="alert"
            className="flex flex-col gap-1.5 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-xs"
          >
            <p className="flex items-center gap-1.5 font-medium text-destructive">
              <AlertTriangle aria-hidden="true" className="size-3.5" />
              {t('leads.bulkConvert.alreadyConverted', { count: alreadyConverted.length })}
            </p>
            <ul className="flex flex-col gap-1 text-muted-foreground">
              {alreadyConverted.map((row) => (
                <li key={row.id}>{leadLabel(row, row.id)}</li>
              ))}
            </ul>
          </div>
        ) : null}

        {blockers ? (
          <div
            role="alert"
            className="flex flex-col gap-1.5 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-xs"
          >
            <p className="flex items-center gap-1.5 font-medium text-destructive">
              <AlertTriangle aria-hidden="true" className="size-3.5" />
              {t('leads.bulkConvert.blocked')}
            </p>
            <ul className="flex flex-col gap-1 text-muted-foreground">
              {blockers.map((blocker) => (
                <li key={blocker.id}>
                  <span className="font-medium text-foreground">
                    {leadLabel(
                      rows.find((row) => row.id === blocker.id),
                      blocker.id,
                    )}
                  </span>{' '}
                  — {t(`leads.bulkConvert.reasons.${blocker.reason}`)}
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <DialogFooter>
          <Button variant="outline" className="bg-card" onClick={() => handleOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            onClick={() => void handleConfirm()}
            disabled={alreadyConverted.length > 0 || convertMutation.isPending}
          >
            {convertMutation.isPending
              ? t('leads.bulkConvert.converting')
              : t('leads.bulkConvert.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
