import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import {
  Building2,
  BriefcaseBusiness,
  Megaphone,
  Plus,
  ShieldCheck,
  StickyNote,
  Trash2,
  type LucideIcon,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import { Textarea } from '@/components/ui/textarea'
import type { CommissionRole, CommissionType } from '@/features/commission-configurations/types'
import { formatQuoteAmount } from './quote-summary'
import { QuoteCommissionSystemDefault } from './quote-commission-system-default'
import { QuoteCommissionOriginBadge } from './quote-commission-origin-badge'
import type { QuoteCommissionsDraft } from './use-quote-commissions-draft'

/** Role column, then type, value, amount and actions: shared by the header strip and every row. */
const ROW_GRID_CLASS = 'md:grid md:grid-cols-[minmax(0,1fr)_6.5rem_8.5rem_7rem_2.5rem] md:items-center md:gap-3'

const ROLE_ICONS: Record<CommissionRole, LucideIcon> = {
  COMMERCIAL: BriefcaseBusiness,
  REPORTER: Megaphone,
  SUPERVISOR: ShieldCheck,
  SUPPLIER: Building2,
}

/** Mobile shows the control labels; from `md` the column header names them instead. */
const CONTROL_LABEL_CLASS = 'text-[11px] font-normal text-muted-foreground md:sr-only'

/** Column header of the role list, visible from `md` up (below it each control carries its own label). */
export function QuoteCommissionRowsHeader() {
  const { t } = useTranslation()

  return (
    <div
      aria-hidden="true"
      className={`hidden border-b bg-muted/40 px-4 py-1.5 text-[11px] font-medium text-muted-foreground ${ROW_GRID_CLASS}`}
    >
      <span>{t('quotes.form.commissions.columns.role')}</span>
      <span>{t('quotes.form.commissions.type')}</span>
      <span>{t('quotes.form.commissions.value')}</span>
      <span className="text-right">{t('quotes.form.commissions.columns.amount')}</span>
      <span />
    </div>
  )
}

interface QuoteCommissionRoleRowProps {
  role: CommissionRole
  commissions: QuoteCommissionsDraft
  disabled: boolean
}

/**
 * One commission role of the line: who it goes to and where the figure comes
 * from on the left, the editable type/value and the resulting amount in
 * columns, then what the rules would assign it underneath.
 */
export function QuoteCommissionRoleRow({ role, commissions, disabled }: QuoteCommissionRoleRowProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const [noteOpen, setNoteOpen] = useState(false)
  const { fields, canMutate } = commissions
  const commission = commissions.byRole.get(role)
  const allowed = commissions.recipientFor(role)
  const unavailable = !commissions.recipientsPending && allowed === null
  const Icon = ROLE_ICONS[role]
  const ids = {
    recipient: `${rowId}-recipient`,
    type: `${rowId}-type`,
    value: `${rowId}-value`,
    amount: `${rowId}-amount`,
    note: `${rowId}-note`,
  }
  const typeOptions: SegmentedControlOption<CommissionType>[] = [
    { value: 'PERCENTAGE', label: t('quotes.form.commissions.typeShort.PERCENTAGE') },
    { value: 'FIXED_AMOUNT', label: t('quotes.form.commissions.typeShort.FIXED_AMOUNT') },
  ]
  const note = commission?.internal_note ?? ''
  const showNote = commission !== undefined && fields.note.visible && (note !== '' || noteOpen)
  const canAddNote = commission !== undefined && fields.note.visible && fields.note.editable && !disabled && !showNote
  // Read mode shows plain figures instead of greyed-out controls.
  const showSystemDefault = commissions.systemDefaultsLoaded && !unavailable
  const typeEditable = !disabled && fields.type.editable
  const valueEditable = !disabled && fields.value.editable

  return (
    <li className="flex flex-col gap-2 px-4 py-3">
      <div className={`flex flex-col gap-3 ${ROW_GRID_CLASS}`}>
        <div className="flex min-w-0 items-center gap-2.5">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
            <Icon aria-hidden="true" className="size-4" />
          </span>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-1.5">
              <h3 className="text-sm font-semibold">{t(`quotes.form.commissions.roles.${role}`)}</h3>
              {commission ? <QuoteCommissionOriginBadge origin={commission.origin} /> : null}
            </div>
            {fields.recipient.visible ? (
              commission ? (
                <>
                  <Label htmlFor={ids.recipient} className="sr-only">{t('quotes.form.commissions.recipient')}</Label>
                  <output
                    id={ids.recipient}
                    title={t('quotes.form.commissions.recipientLocked')}
                    className="block truncate text-xs text-muted-foreground"
                  >
                    {allowed?.name ?? commission.recipient?.name ?? `#${commission.recipient_id}`}
                  </output>
                </>
              ) : allowed ? (
                <p className="truncate text-xs text-muted-foreground">
                  <span className="sr-only">{t('quotes.form.commissions.selectedRecipient')}: </span>
                  <span>{allowed.name}</span>
                </p>
              ) : null
            ) : null}
          </div>
        </div>

        {commission ? (
          <div className="grid grid-cols-[auto_minmax(0,1fr)] items-end gap-2 md:contents">
            <div className="flex flex-col gap-1">
              <Label id={ids.type} className={CONTROL_LABEL_CLASS}>{t('quotes.form.commissions.type')}</Label>
              {!fields.type.visible ? <span /> : typeEditable ? (
                <SegmentedControl
                  aria-labelledby={ids.type}
                  value={commission.commission_type}
                  options={typeOptions}
                  onValueChange={(value) => commissions.patch(role, { commission_type: value })}
                  className="min-w-24 flex-nowrap p-0.5"
                />
              ) : (
                <span aria-labelledby={ids.type} className="flex h-8 items-center text-xs">
                  {t(`commissionConfigurations.options.commission_type.${commission.commission_type}`)}
                </span>
              )}
            </div>
            <div className="flex min-w-0 flex-col gap-1">
              <Label htmlFor={ids.value} className={CONTROL_LABEL_CLASS}>{t('quotes.form.commissions.value')}</Label>
              {!fields.value.visible ? null : valueEditable ? (
                <div className="relative">
                  <Input
                    id={ids.value}
                    type="number"
                    min={0}
                    step="0.0001"
                    value={commission.value}
                    onChange={(event) => commissions.patch(role, { value: Number(event.target.value) })}
                    className="h-8 [appearance:textfield] pr-7 text-right tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                  />
                  <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-xs text-muted-foreground">
                    {commission.commission_type === 'PERCENTAGE' ? '%' : '€'}
                  </span>
                </div>
              ) : (
                <output id={ids.value} className="flex h-8 items-center text-sm tabular-nums">
                  {formatQuoteAmount(commission.value)}
                  {commission.commission_type === 'PERCENTAGE' ? '%' : ' €'}
                </output>
              )}
            </div>
            <div className="flex flex-col gap-1 md:text-right">
              <Label htmlFor={ids.amount} className={`md:justify-end ${CONTROL_LABEL_CLASS}`}>{t('quotes.form.commissions.calculated')}</Label>
              <output id={ids.amount} className="flex h-8 items-center text-sm font-semibold tabular-nums md:justify-end">
                {formatQuoteAmount(commissions.amountOf(commission))}
              </output>
            </div>
            <div className="flex justify-end">
              {canMutate ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('quotes.form.commissions.remove')}
                  title={t('quotes.form.commissions.remove')}
                  className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                  onClick={() => void commissions.remove(role)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              ) : null}
            </div>
          </div>
        ) : (
          <div className="flex flex-wrap items-center justify-between gap-2 md:col-span-4">
            <p className="text-xs text-muted-foreground">
              {unavailable ? t('quotes.form.commissions.roleUnavailable') : t('quotes.form.commissions.empty')}
            </p>
            {canMutate && !unavailable ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-7 bg-card text-xs"
                disabled={allowed === null}
                onClick={() => allowed && commissions.add(role, allowed)}
              >
                <Plus aria-hidden="true" className="size-3.5" />
                {t('quotes.form.commissions.addManual')}
              </Button>
            ) : null}
          </div>
        )}
      </div>

      {commission && unavailable ? (
        <p role="alert" className="text-xs text-destructive md:pl-[2.625rem]">
          {t('quotes.form.commissions.recipientStale')}
        </p>
      ) : null}

      {showNote ? (
        <div className="flex flex-col gap-1 md:pl-[2.625rem]">
          <Label htmlFor={ids.note} className="text-[11px] font-normal text-muted-foreground">
            {t('quotes.form.commissions.note')}
          </Label>
          <Textarea
            id={ids.note}
            value={note}
            autoFocus={noteOpen && note === ''}
            disabled={disabled || !fields.note.editable}
            onChange={(event) => commissions.patch(role, { internal_note: event.target.value || null })}
            className="min-h-8 py-1.5 text-xs md:text-xs"
          />
        </div>
      ) : null}

      {showSystemDefault || canAddNote ? (
        <div className="flex flex-wrap items-center justify-between gap-2 md:pl-[2.625rem]">
          {showSystemDefault ? (
            <QuoteCommissionSystemDefault
              systemDefault={commissions.systemDefaultFor(role)}
              current={commission}
              commissionBase={commissions.commissionBase}
              canApply={canMutate}
              onApply={commissions.applySystemDefault}
            />
          ) : <span />}
          {canAddNote ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-6 gap-1 bg-card px-2 text-[11px] text-muted-foreground"
              onClick={() => setNoteOpen(true)}
            >
              <StickyNote aria-hidden="true" className="size-3.5" />
              {t('quotes.form.commissions.addNote')}
            </Button>
          ) : null}
        </div>
      ) : null}
    </li>
  )
}
