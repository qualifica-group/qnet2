import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Shared state/callback threaded through `gridOptions.context` (review-grid.tsx),
 * mirroring `ReviewOperatorGridContext`: the campaign column opens a popup and
 * applies through the same mutation whichever row triggered it (spec 0108 D-5).
 */
export interface ReviewCampaignGridContext {
  onApplyCampaign: (
    row: ImportRunRowItem,
    campaignId: number | null,
    node: IRowNode<ImportRunRowItem>,
  ) => Promise<void>
}

export interface ReviewCampaignCellParams
  extends ICellRendererParams<ImportRunRowItem, unknown, ReviewCampaignGridContext> {
  /** Forces plain text with no popup affordance, mirroring the other per-row cells (spec 0034 AC-013). */
  readOnly?: boolean
}

/**
 * Per-row campaign cell, rendered only on a run that reads campaigns from a
 * file column. A resolved row shows `CODE — Name`; an unresolved one shows the
 * code the file carried, marked as unmatched, since that is the row's actual
 * blocker. Not `readOnly`, the cell is a button opening the campaign picker.
 */
export function ReviewCampaignCell({ data, node, context, readOnly }: ReviewCampaignCellParams) {
  const { t } = useTranslation('importWizard')
  const [open, setOpen] = useState(false)

  if (!data) return null

  const campaign = data.campaign ?? null
  const fileCode = String(data.values.campaign_code ?? '').trim()
  const label = campaign ? `${campaign.code} — ${campaign.name}` : fileCode || t('review.campaign.missing')

  const content = campaign ? (
    <span className="truncate">{label}</span>
  ) : (
    <span className="flex items-center gap-1 truncate text-destructive">
      <AlertTriangle className="size-3.5 shrink-0" aria-hidden />
      <span className="truncate">{label}</span>
    </span>
  )

  if (readOnly) {
    return <span className="truncate text-xs text-muted-foreground">{label}</span>
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <button
          type="button"
          className="block w-full truncate text-left text-xs underline-offset-2 hover:underline"
          aria-label={t('review.campaign.editLabel')}
        >
          {content}
        </button>
      </DialogTrigger>
      <DialogContent size="sm">
        <ReviewCampaignDialogBody
          row={data}
          node={node}
          onApplyCampaign={context.onApplyCampaign}
          onClose={() => setOpen(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface ReviewCampaignDialogBodyProps {
  row: ImportRunRowItem
  node: IRowNode<ImportRunRowItem>
  onApplyCampaign: ReviewCampaignGridContext['onApplyCampaign']
  onClose: () => void
}

/**
 * The popup's content: a controlled `AsyncPaginatedSelect` seeded from the
 * row's currently resolved campaign, plus an "use the file code" shortcut that
 * clears the pin and hands the row back to the backend's own code resolution
 * (`campaign_id: null`). Only Applica calls `onApplyCampaign`.
 */
function ReviewCampaignDialogBody({ row, node, onApplyCampaign, onClose }: ReviewCampaignDialogBodyProps) {
  const { t } = useTranslation('importWizard')
  const [campaignId, setCampaignId] = useState<number | null>(row.campaign_id ?? null)
  const [isApplying, setIsApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Step 1: PATCH the popup's current campaign id (or `null` to unpin).
  // Step 2: on success close the popup — the grid row and the run counters are
  // refreshed by the caller; on failure keep it open with the error inline,
  // since the row's status depends on this choice.
  function handleApply() {
    setIsApplying(true)
    setError(null)
    onApplyCampaign(row, campaignId, node)
      .then(() => onClose())
      .catch((cause: unknown) => setError(resolveImportWizardErrorMessage(cause, t)))
      .finally(() => setIsApplying(false))
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('review.campaign.title')}</DialogTitle>
        <DialogDescription>{t('review.campaign.description')}</DialogDescription>
      </DialogHeader>

      <AsyncPaginatedSelect
        resource={CAMPAIGNS_FOR_SELECT_RESOURCE}
        value={campaignId}
        onChange={setCampaignId}
        selectedItem={row.campaign ? { id: row.campaign.id, label: row.campaign.name } : null}
        disabled={isApplying}
        labels={{
          placeholder: t('review.campaign.placeholder'),
          searchPlaceholder: t('review.campaign.searchPlaceholder'),
          empty: t('review.campaign.empty'),
          error: t('review.campaign.selectError'),
          clearLabel: t('review.campaign.selectClear'),
          triggerLabel: t('review.campaign.title'),
          retry: t('review.campaign.retry'),
        }}
      />

      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}

      <DialogFooter>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="mr-auto"
          onClick={() => setCampaignId(null)}
          disabled={isApplying || campaignId === null}
        >
          {t('review.campaign.useFileCode')}
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isApplying}>
          {t('review.campaign.cancel')}
        </Button>
        <Button type="button" size="sm" onClick={handleApply} disabled={isApplying}>
          {t('review.campaign.apply')}
        </Button>
      </DialogFooter>
    </>
  )
}
