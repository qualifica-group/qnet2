import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { calculateCommissionAmount, roundCommission } from './commission-calculator'
import type { CommissionRole } from '@/features/commission-configurations/types'
import type { QuoteLineCommissionInput } from './types'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useConfirm } from '@/components/confirm-dialog-context'

const ROLES: CommissionRole[] = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER']
const recipientContract = {
  COMMERCIAL: { resource: 'referents', type: 'referent' as const },
  REPORTER: { resource: 'referents', type: 'referent' as const },
  SUPERVISOR: { resource: 'users', type: 'user' as const },
  SUPPLIER: { resource: 'registries', type: 'registry' as const },
}

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  lineNumber: number
  productName: string
  quantity: number | null
  unitPrice: number | null
  commissions: QuoteLineCommissionInput[]
  disabled: boolean
  onSave: (commissions: QuoteLineCommissionInput[]) => void
}

export function QuoteCommissionsDialog(props: Props) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const confirm = useConfirm()
  const [draft, setDraft] = useState(props.commissions)
  const lineNet = roundCommission((props.quantity ?? 0) * (props.unitPrice ?? 0))
  const byRole = useMemo(() => new Map(draft.map((item) => [item.recipient_role, item])), [draft])
  const patch = (role: CommissionRole, next: Partial<QuoteLineCommissionInput>) =>
    setDraft((current) =>
      current.map((item) =>
        item.recipient_role === role
          ? { ...item, ...next, origin: 'MANUAL_OVERRIDE' as const }
          : item,
      ),
    )
  const add = (role: CommissionRole) => {
    const contract = recipientContract[role]
    setDraft((current) => [...current, {
      recipient_role: role,
      recipient_type: contract.type,
      recipient_id: 0,
      commission_type: 'PERCENTAGE',
      value: 0,
      internal_note: null,
      origin: 'MANUAL_OVERRIDE',
      commission_configuration_id: null,
    }])
  }
  const remove = async (role: CommissionRole, persisted: boolean) => {
    if (persisted) {
      const accepted = await confirm({
        tone: 'destructive',
        title: t('quotes.form.commissions.removeTitle'),
        description: t('quotes.form.commissions.removeDescription'),
        confirmLabel: t('quotes.form.commissions.removeConfirm'),
        cancelLabel: t('common.cancel'),
      })
      if (!accepted) return
    }
    setDraft((current) => current.filter((item) => item.recipient_role !== role))
  }

  const collectionPermission = fieldPermission('commissions')
  const canMutateCollection =
    !props.disabled && collectionPermission.visible && collectionPermission.editable
  const recipientPermission = fieldPermission('commission_recipient')
  const typePermission = fieldPermission('commission_type')
  const valuePermission = fieldPermission('commission_value')
  const notePermission = fieldPermission('commission_internal_note')

  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-4xl gap-0 overflow-hidden p-0">
        <DialogHeader className="border-b p-4">
          <DialogTitle>{t('quotes.form.commissions.title', { product: props.productName, n: props.lineNumber })}</DialogTitle>
          <DialogDescription>{t('quotes.form.commissions.description')}</DialogDescription>
        </DialogHeader>
        <div className="grid gap-3 overflow-y-auto p-4">
          {ROLES.map((role) => {
            const commission = byRole.get(role)
            if (!commission) {
              return (
                <section key={role} className="rounded-md border p-3">
                  <h3 className="text-sm font-semibold">{t(`quotes.form.commissions.roles.${role}`)}</h3>
                  <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground">{t('quotes.form.commissions.empty')}</p>
                    {canMutateCollection ? <Button type="button" size="sm" variant="outline" onClick={() => add(role)}><Plus aria-hidden="true" />{t('quotes.form.commissions.addManual')}</Button> : null}
                  </div>
                </section>
              )
            }
            const labels = {
              placeholder: t('quotes.form.selectPlaceholder'), searchPlaceholder: t('quotes.form.commissions.recipientSearch'),
              empty: t('quotes.form.selectEmpty'), error: t('quotes.form.selectError'), clearLabel: t('common.clear'),
              triggerLabel: t('quotes.form.commissions.recipient'), retry: t('common.retry'),
            }
            const amount = calculateCommissionAmount(commission.commission_type, commission.value, lineNet)
            const recipientId = `commission-${role}-recipient`
            const typeId = `commission-${role}-type`
            const amountId = `commission-${role}-amount`
            return (
              <section key={role} className="rounded-md border p-3">
                <header className="mb-3 flex items-center justify-between gap-2">
                  <h3 className="text-sm font-semibold">{t(`quotes.form.commissions.roles.${role}`)}</h3>
                  <Badge variant="secondary">{t(`quotes.form.commissions.origins.${commission.origin}`)}</Badge>
                </header>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {recipientPermission.visible ? <div className="grid gap-2">
                    <Label htmlFor={recipientId}>{t('quotes.form.commissions.recipient')}</Label>
                    <AsyncPaginatedSelect
                      id={recipientId}
                      resource={recipientContract[role].resource}
                      value={commission.recipient_id || null}
                      onChange={(nextRecipientId) => patch(role, { recipient_id: nextRecipientId ?? 0 })}
                      onItemChange={(item) => patch(role, {
                        recipient: item ? { id: item.id, name: item.label } : null,
                      })}
                      selectedItem={commission.recipient_id ? { id: commission.recipient_id, label: commission.recipient?.name ?? `#${commission.recipient_id}` } : null}
                      disabled={props.disabled || !recipientPermission.editable}
                      labels={labels}
                    />
                  </div> : null}
                  {typePermission.visible ? <div className="grid gap-2">
                    <Label htmlFor={typeId}>{t('quotes.form.commissions.type')}</Label>
                    <Select value={commission.commission_type} onValueChange={(value) => patch(role, { commission_type: value as 'FIXED_AMOUNT' | 'PERCENTAGE' })} disabled={props.disabled || !typePermission.editable}>
                      <SelectTrigger id={typeId}><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="FIXED_AMOUNT">{t('commissionConfigurations.options.commission_type.FIXED_AMOUNT')}</SelectItem>
                        <SelectItem value="PERCENTAGE">{t('commissionConfigurations.options.commission_type.PERCENTAGE')}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div> : null}
                  {valuePermission.visible ? <div className="grid gap-2">
                    <Label htmlFor={`commission-${role}-value`}>{t('quotes.form.commissions.value')}</Label>
                    <div className="flex items-center gap-2">
                      <Input id={`commission-${role}-value`} type="number" min={0} step="0.0001" value={commission.value} disabled={props.disabled || !valuePermission.editable} onChange={(event) => patch(role, { value: Number(event.target.value) })} />
                      <span className="text-sm text-muted-foreground" aria-hidden="true">{commission.commission_type === 'PERCENTAGE' ? '%' : '€'}</span>
                    </div>
                  </div> : null}
                  <div className="grid gap-2">
                    <Label htmlFor={amountId}>{t('quotes.form.commissions.calculated')}</Label>
                    <output id={amountId} className="flex h-9 items-center rounded-md border bg-muted/40 px-3 font-semibold tabular-nums">{amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</output>
                  </div>
                  {notePermission.visible ? <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor={`commission-${role}-note`}>{t('quotes.form.commissions.note')}</Label>
                    <Textarea id={`commission-${role}-note`} value={commission.internal_note ?? ''} disabled={props.disabled || !notePermission.editable} onChange={(event) => patch(role, { internal_note: event.target.value || null })} />
                  </div> : null}
                </div>
                {canMutateCollection ? <Button type="button" variant="ghost" size="sm" className="mt-2 text-destructive" onClick={() => void remove(role, commission.id !== undefined)}><Trash2 aria-hidden="true" />{t('quotes.form.commissions.remove')}</Button> : null}
              </section>
            )
          })}
        </div>
        <DialogFooter className="border-t p-4">
          <Button type="button" variant="outline" onClick={() => props.onOpenChange(false)}>{props.disabled ? t('quotes.form.commissions.close') : t('common.cancel')}</Button>
          {canMutateCollection ? <Button type="button" disabled={draft.some((item) => item.recipient_id <= 0)} onClick={() => { props.onSave(draft); props.onOpenChange(false) }}>{t('quotes.form.commissions.save')}</Button> : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
