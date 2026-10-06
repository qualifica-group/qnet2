/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { fetchQuote, quoteDetailQueryKey } from '@/features/quotes/api'
import { QuoteForm } from '@/features/quotes/quote-form'
import { QuoteDetailView } from '@/features/quotes/quote-detail'
import {
  QUOTE_CREATE_OPPORTUNITY_PARAM,
  QUOTE_CREATE_PRODUCT_IDS_PARAM,
  QUOTE_CREATE_REGISTRY_PARAM,
} from '@/features/quotes/quote-create-params'
import { parseEntityId } from '@/routes/entity-id'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleCreateParams,
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { QuoteDetail } from '@/features/quotes/types'

/**
 * Content-only `quotes` screens for the module registry (spec 0042): fetch +
 * the existing presentational view/form, no page chrome. `quotes` uses
 * `OPEN_MODE_PAGE` (the record + always-visible economic summary does not fit
 * a modal Sheet well); the generic `ModuleDetailPage`/`ModuleFormPage` host
 * these screens for `new`/`:id`, only the list route (`/quotes`) is declared
 * by hand in `router.tsx`, same as every module.
 *
 * Spec 0197 (the Commesse model): the detail edits its fields in place, so
 * `onEdit` is never used; each save reports through `onChanged` (the modal
 * host refreshes its grid and keeps the record open).
 */
export function QuoteDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: quote,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(quoteDetailQueryKey(id), () => fetchQuote(id))

  if (isError) {
    return (
      <DetailError
        message={t('quotes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !quote) {
    return <DetailLoading />
  }

  return <QuoteDetailView quote={quote} onChanged={onChanged} />
}

/**
 * Reads `opportunity_id` (and, user directive 2026-08-31, the `product_ids`
 * that seed the offer rows) from `mode.params` (spec 0045/0067 AC-050), the
 * same single channel `OpportunityFormScreen` reads `lead_id` from: the modal
 * Sheet (opportunity detail's "Crea Offerta" panel) hands the params straight
 * through, while `ModuleFormPage` would convert a deep-link query string into
 * the same shape. `opportunity_id` can therefore arrive as either a `number`
 * (modal caller) or a `string` — normalized with `parseEntityId`.
 */
export function QuoteFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  // Leaving an offer being created — Cancel, the Sheet's X/overlay/Esc, a
  // link, a reload — always asks first (spec 0195 D-9, applied by spec 0197).
  const leaveGuard = useFormLeaveGuard({
    title: t('quotes.form.leaveConfirm.title'),
    description: t('quotes.form.leaveConfirm.description'),
    confirmLabel: t('quotes.form.leaveConfirm.confirm'),
    cancelLabel: t('quotes.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: QuoteDetail) => {
    queryClient.invalidateQueries({ queryKey: quoteDetailQueryKey(saved.id) })
    // Saved: the navigation to the new offer's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  // No edit form: the detail edits in place, and the registry generates no
  // `:id/edit` route (`generateEditRoute: false`).
  if (mode.type !== 'create') {
    return null
  }

  const opportunityId = parseEntityId(String(mode.params?.[QUOTE_CREATE_OPPORTUNITY_PARAM] ?? ''))
  // `product_ids` travels verbatim (a comma-separated string either way) —
  // `useQuoteCreateDefaults` parses it where it seeds the offer rows.
  const productIds = mode.params?.[QUOTE_CREATE_PRODUCT_IDS_PARAM]
  // Spec 0199: the anagrafica's Offerte tab narrows the Opportunita' picker.
  const registryId = parseEntityId(String(mode.params?.[QUOTE_CREATE_REGISTRY_PARAM] ?? ''))
  const params: ModuleCreateParams | undefined =
    opportunityId !== null
      ? {
          [QUOTE_CREATE_OPPORTUNITY_PARAM]: opportunityId,
          ...(productIds !== undefined ? { [QUOTE_CREATE_PRODUCT_IDS_PARAM]: productIds } : {}),
        }
      : registryId !== null
        ? { [QUOTE_CREATE_REGISTRY_PARAM]: registryId }
        : undefined

  return (
    <>
      {leaveGuard.navigationGuard}
      <QuoteForm
        mode={{ type: 'create', params }}
        onSuccess={handleSuccess}
        onCancel={() => void handleCancel()}
      />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'quotes',
  basePath: '/quotes',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.quotes',
  DetailScreen: QuoteDetailScreen,
  FormScreen: QuoteFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
