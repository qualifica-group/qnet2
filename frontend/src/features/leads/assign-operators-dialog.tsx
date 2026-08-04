import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CheckCircle2, MapPin, Scale, User, UserCheck, Users } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

/** Precompiled Sede seed (spec 0048 AC-031), when the selection shares one. */
export interface AssignOperatorsDialogSite {
  id: number
  label: string
}

export type AssignmentMode = 'single' | 'balanced'

/**
 * Copy that names the assigned entity ("… lead selezionati", "Distribuisce i
 * lead selezionati…"). Everything else in the dialog is entity-neutral and
 * stays on the shared strings. `title`, optional (spec 0079): omitted, the
 * header keeps the Lead wording (`leads.assign.title`) — the transfer flow is
 * the only caller that overrides it.
 */
export interface AssignOperatorsDialogCopy {
  title?: string
  description: string
  modeHints: Record<AssignmentMode, string>
}

/** Input handed to `onAssign`, mirroring the `POST /leads/assign-operators` request body. */
export interface AssignOperatorsDialogInput {
  operational_site_id: number
  mode: AssignmentMode
  operator_id?: number
}

/**
 * Presentation tokens per assignment mode. Each mode owns a distinct semantic
 * accent (emerald = workload split, sky = single operator) so the two cards
 * read as different actions at a glance. Full class strings are inlined (not
 * built dynamically) so Tailwind's JIT keeps them.
 */
const ASSIGNMENT_MODES: ReadonlyArray<{
  mode: AssignmentMode
  icon: LucideIcon
  /** Icon chip styling while selected. */
  chip: string
  /** Card border/tint/ring while selected. */
  card: string
  /** Accent color for the selected check mark. */
  accent: string
}> = [
  {
    mode: 'balanced',
    icon: Scale,
    chip: 'bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/25 dark:text-emerald-400',
    card: 'border-emerald-500/60 bg-emerald-500/[0.07] ring-2 ring-emerald-500/25',
    accent: 'text-emerald-600 dark:text-emerald-400',
  },
  {
    mode: 'single',
    icon: UserCheck,
    chip: 'bg-sky-500/15 text-sky-600 ring-1 ring-sky-500/25 dark:text-sky-400',
    card: 'border-sky-500/60 bg-sky-500/[0.07] ring-2 ring-sky-500/25',
    accent: 'text-sky-600 dark:text-sky-400',
  },
]

/** Crisp, compact styling shared by the Sede/Operatore selects. */
const SELECT_CLASS = 'h-8 bg-card text-xs shadow-sm transition-colors hover:border-ring/50'

export interface AssignOperatorsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** How many leads/rows are selected; drives the title/description copy. */
  selectionCount: number
  defaultSiteId?: number | null
  defaultSite?: AssignOperatorsDialogSite | null
  /**
   * The two entity-specific sentences, resolved by the consumer (the dialog
   * itself stays domain-agnostic). Omitted, both fall back to the Lead
   * wording, so the leads/import call sites read exactly as before.
   */
  copy?: AssignOperatorsDialogCopy
  /**
   * Additive, opt-in (spec 0079): when set, Step 1 (the mode picker) never
   * renders, `mode` is fixed to this value instead of user-picked, and the
   * Operatore field is always shown (not only for `single`). The three
   * pre-existing consumers (leads, this module's own assignment, the import
   * wizard) leave it `undefined` and keep their behavior byte-for-byte
   * (AC-029).
   */
  lockedMode?: AssignmentMode
  /**
   * Wired by the consumer to its own endpoint (the Lead table via
   * `useAssignOperators`, the import review bar via its own PATCH). The
   * dialog never calls the API itself: it only collects the input, shows a
   * pending state, and closes on success. A rejection is assumed already
   * surfaced by the caller (toast) and just keeps the dialog open with the
   * current picks so the user can retry.
   */
  onAssign: (input: AssignOperatorsDialogInput) => Promise<void>
}

/**
 * Shared "Assegna operatori" popup (spec 0048). The flow is sequential: the
 * user first picks the assignment mode — "Smistamento equo" (`balanced`) or
 * "Assegna a operatore" (`single`) — then the Sede, and (only for `single`)
 * the Operatore filtered by that Sede (AC-030). A single confirm action
 * commits the pick. Reused as-is by the Lead table's bulk action and the
 * import review bar. With `lockedMode` (spec 0079, additive) the mode step is
 * skipped entirely and the Operatore is always shown — the contact-transfer
 * flow's own use, which never lets the user pick a mode.
 */
export function AssignOperatorsDialog({
  open,
  onOpenChange,
  selectionCount,
  defaultSiteId,
  defaultSite,
  copy,
  lockedMode,
  onAssign,
}: AssignOperatorsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
        <AssignOperatorsDialogBody
          selectionCount={selectionCount}
          defaultSiteId={defaultSiteId}
          defaultSite={defaultSite}
          copy={copy}
          lockedMode={lockedMode}
          onAssign={onAssign}
          onClose={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface AssignOperatorsDialogBodyProps {
  selectionCount: number
  defaultSiteId?: number | null
  defaultSite?: AssignOperatorsDialogSite | null
  copy?: AssignOperatorsDialogCopy
  lockedMode?: AssignmentMode
  onAssign: AssignOperatorsDialogProps['onAssign']
  onClose: () => void
}

/**
 * Radix unmounts `DialogContent`'s subtree while closed, so keeping this
 * state in its own component (rather than in `AssignOperatorsDialog` itself,
 * which the consumer keeps mounted across opens) is what makes every open a
 * fresh selection instead of carrying over the previous one.
 */
function AssignOperatorsDialogBody({
  selectionCount,
  defaultSiteId,
  defaultSite,
  copy,
  lockedMode,
  onAssign,
  onClose,
}: AssignOperatorsDialogBodyProps) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<AssignmentMode | null>(lockedMode ?? null)
  const [siteId, setSiteId] = useState<number | null>(defaultSiteId ?? defaultSite?.id ?? null)
  const [operatorId, setOperatorId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // A Sede change re-scopes the whole operator list: the previous pick can
  // no longer be assumed to belong to it, so it is always cleared.
  function handleSiteChange(nextSiteId: number | null) {
    setSiteId(nextSiteId)
    setOperatorId(null)
  }

  // Locked mode (spec 0079): the step-1 picker never renders and the
  // Operatore field is always shown, so submission always needs both Sede
  // and Operatore. Free mode keeps the original per-mode gating: single
  // needs both, balanced needs only the Sede.
  const canSubmit = lockedMode
    ? siteId !== null && operatorId !== null
    : mode !== null && siteId !== null && (mode === 'balanced' || operatorId !== null)

  function handleAssign() {
    if (!canSubmit || siteId === null) {
      return
    }
    const effectiveMode = (lockedMode ?? mode) as AssignmentMode
    setIsSubmitting(true)
    onAssign({
      operational_site_id: siteId,
      mode: effectiveMode,
      ...(lockedMode || effectiveMode === 'single' ? { operator_id: operatorId as number } : {}),
    })
      .then(() => onClose())
      .catch(() => {
        // Already surfaced via toast by the caller; keep the picks so the
        // user can retry without reselecting.
      })
      .finally(() => setIsSubmitting(false))
  }

  const effectiveMode = lockedMode ?? mode
  const ConfirmIcon = effectiveMode === 'single' ? UserCheck : Scale
  // Locked mode always shows the Operatore field (spec 0079 AC-027); free
  // mode keeps it gated behind the user's own `single` pick.
  const showOperatorField = lockedMode !== undefined || mode === 'single'

  return (
    <>
      {/* Header band: brand-tinted strip with an icon chip for identity. */}
      <div className="flex items-start gap-3 border-b bg-gradient-to-br from-card to-primary/[0.06] px-4 pt-4 pb-3.5">
        <span
          aria-hidden="true"
          className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
        >
          <Users className="size-4.5" />
        </span>
        <DialogHeader className="flex-1 gap-1">
          <DialogTitle className="text-sm">{copy?.title ?? t('leads.assign.title')}</DialogTitle>
          <DialogDescription className="text-xs">
            {copy?.description ?? t('leads.assign.description', { count: selectionCount })}
          </DialogDescription>
        </DialogHeader>
      </div>

      <div className="space-y-4 px-4 py-4">
        {/* Step 1: pick the assignment mode — skipped entirely when locked (spec 0079 AC-027). */}
        {!lockedMode && (
          <div className="space-y-2">
            <Label className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
              {t('leads.assign.mode.label')}
            </Label>
            <div role="radiogroup" aria-label={t('leads.assign.mode.label')} className="flex flex-col gap-2">
              {ASSIGNMENT_MODES.map((entry) => {
                const { mode: value, icon: Icon } = entry
                const selected = mode === value
                return (
                  <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={selected}
                    aria-label={t(`leads.assign.actions.${value}`)}
                    disabled={isSubmitting}
                    onClick={() => setMode(value)}
                    className={cn(
                      'group relative flex items-start gap-3 rounded-xl border bg-card p-3 text-left transition-all',
                      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:ring-offset-background',
                      'disabled:pointer-events-none disabled:opacity-50',
                      selected
                        ? cn(entry.card, 'shadow-sm')
                        : 'border-border hover:border-foreground/20 hover:bg-muted/40 hover:shadow-sm motion-safe:hover:-translate-y-0.5',
                    )}
                  >
                    <span
                      aria-hidden="true"
                      className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                        selected ? entry.chip : 'bg-muted text-muted-foreground group-hover:bg-muted/70',
                      )}
                    >
                      <Icon className="size-4" />
                    </span>
                    <span className="min-w-0 flex-1 space-y-0.5 pr-4">
                      <span className="block text-xs font-semibold text-foreground">
                        {t(`leads.assign.actions.${value}`)}
                      </span>
                      <span className="block text-[11px] leading-snug text-muted-foreground">
                        {copy?.modeHints[value] ?? t(`leads.assign.actions.${value}Hint`)}
                      </span>
                    </span>
                    {selected && (
                      <CheckCircle2
                        aria-hidden="true"
                        className={cn(
                          'absolute top-2.5 right-2.5 size-4 shrink-0',
                          entry.accent,
                          'motion-safe:animate-in motion-safe:fade-in motion-safe:zoom-in-75',
                        )}
                      />
                    )}
                  </button>
                )
              })}
            </div>
          </div>
        )}

        {/* Step 2: pick the Sede (always) and the Operatore (single, or locked — spec 0079). */}
        {mode !== null && (
          <div className="space-y-3 rounded-xl border bg-gradient-to-b from-card to-muted/20 p-3 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-top-1">
            <div className="space-y-1.5">
              <Label
                htmlFor="assign-operators-site"
                className="flex items-center gap-1.5 text-xs font-medium"
              >
                <MapPin className="size-3.5 text-primary" aria-hidden="true" />
                {t('leads.assign.site.label')}
              </Label>
              <AsyncPaginatedSelect
                id="assign-operators-site"
                resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                value={siteId}
                onChange={handleSiteChange}
                selectedItem={defaultSite ? { id: defaultSite.id, label: defaultSite.label } : null}
                disabled={isSubmitting}
                className={SELECT_CLASS}
                labels={{
                  placeholder: t('leads.assign.site.placeholder'),
                  searchPlaceholder: t('leads.assign.site.searchPlaceholder'),
                  empty: t('leads.assign.site.empty'),
                  error: t('leads.assign.site.selectError'),
                  clearLabel: t('leads.assign.site.selectClear'),
                  triggerLabel: t('leads.assign.site.label'),
                  retry: t('leads.assign.site.retry'),
                }}
              />
            </div>

            {showOperatorField && (
              <div className="space-y-1.5">
                <Label
                  htmlFor="assign-operators-operator"
                  className="flex items-center gap-1.5 text-xs font-medium"
                >
                  <User className="size-3.5 text-sky-600 dark:text-sky-400" aria-hidden="true" />
                  {t('leads.assign.operator.label')}
                </Label>
                <AsyncPaginatedSelect
                  id="assign-operators-operator"
                  resource={USERS_FOR_SELECT_RESOURCE}
                  value={operatorId}
                  onChange={setOperatorId}
                  showAvatar
                  disabled={isSubmitting || siteId === null}
                  params={siteId !== null ? { operational_site_id: siteId } : undefined}
                  className={SELECT_CLASS}
                  labels={{
                    placeholder: t('leads.assign.operator.placeholder'),
                    searchPlaceholder: t('leads.assign.operator.searchPlaceholder'),
                    empty: t('leads.assign.operator.empty'),
                    error: t('leads.assign.operator.selectError'),
                    clearLabel: t('leads.assign.operator.selectClear'),
                    triggerLabel: t('leads.assign.operator.label'),
                    retry: t('leads.assign.operator.retry'),
                  }}
                />
                <p className="text-[11px] text-muted-foreground">
                  {siteId === null ? t('leads.assign.operator.disabledHint') : t('leads.assign.operator.hint')}
                </p>
              </div>
            )}
          </div>
        )}
      </div>

      <DialogFooter className="border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3.5">
        <Button
          type="button"
          size="sm"
          className="w-full gap-1.5 shadow-sm shadow-primary/20 transition-all hover:shadow-md hover:shadow-primary/25 motion-safe:active:translate-y-px"
          onClick={handleAssign}
          disabled={!canSubmit || isSubmitting}
        >
          <ConfirmIcon className="size-3.5" aria-hidden="true" />
          {isSubmitting ? t('leads.assign.actions.assigning') : t('leads.assign.actions.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}
