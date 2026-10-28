import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Star, Truck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { RecordLink } from '@/components/detail/record-link'
import { UserAvatar } from '@/components/user-avatar'
import { Badge } from '@/components/ui/badge'
import { formatEuro } from '@/features/invoices/invoice-format'
import { computeProductMargin, type ProductMargin } from '@/features/products/product-margin'
import { SUPPLIER_MOBILE_LABEL, SUPPLIER_TABLE_GRID, SUPPLIER_TABLE_ROW_GRID } from '@/features/products/product-suppliers-layout'
import type { ProductRecordSupplier } from '@/features/products/product-record'
import { SupplierCommissionNote } from '@/features/products/supplier-commission-note'

const PERCENT_FORMAT = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 })

/** "Predefinito" with a star: the default is a property of the supplier, read next to its name. */
export function DefaultSupplierBadge() {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className="gap-1 px-1.5 py-0 text-[11px]">
      <Star className="size-3 fill-current" aria-hidden="true" />
      {t('products.form.suppliers.default')}
    </Badge>
  )
}

/** "€ 40,00 · 26,7%": the margin over the price, destructive when the supplier sells at a loss. */
export function SupplierMarginValue({ margin, className }: { margin: ProductMargin | null; className?: string }) {
  if (margin === null) {
    return <span className={cn('text-muted-foreground', className)}>—</span>
  }
  return (
    <span className={cn('tabular-nums', margin.amount < 0 && 'text-destructive', className)}>
      {formatEuro(margin.amount)}
      {margin.percent !== null ? <span className="ml-1 text-[11px] text-muted-foreground">{PERCENT_FORMAT.format(margin.percent)}%</span> : null}
    </span>
  )
}

function SupplierIdentity({ supplier }: { supplier: ProductRecordSupplier }) {
  return (
    <div className="flex min-w-0 items-center gap-2">
      <UserAvatar name={supplier.name} size="sm" className="shrink-0" />
      <RecordLink domain="registries" id={supplier.supplier_id} className="truncate font-medium">
        {supplier.name}
      </RecordLink>
      {supplier.is_default ? <DefaultSupplierBadge /> : null}
    </div>
  )
}

/** A commission makes the margin depend on the commission, not on a cost: it is not computed (D-22). */
function marginOf(supplier: ProductRecordSupplier): ProductMargin | null {
  return supplier.commission ? null : computeProductMargin(supplier.cost, supplier.price)
}

function CostValue({ supplier }: { supplier: ProductRecordSupplier }) {
  return supplier.commission ? (
    <SupplierCommissionNote commission={supplier.commission} />
  ) : (
    <span className="tabular-nums">{formatEuro(supplier.cost)}</span>
  )
}

function EmptySuppliers() {
  const { t } = useTranslation()
  return (
    <div className="flex items-center gap-3 rounded-lg border border-dashed px-3 py-3">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <Truck className="size-4" aria-hidden="true" />
      </span>
      <div className="min-w-0">
        <p className="text-sm font-medium">{t('products.form.suppliers.emptyTitle')}</p>
        <p className="text-xs text-muted-foreground">{t('products.form.suppliers.emptyHint')}</p>
      </div>
    </div>
  )
}

/** Names a value only while its row is stacked (`variant` picks the table's or the editor's breakpoint). */
export function SupplierMobileLabel({ children, variant = 'table' }: { children: string; variant?: keyof typeof SUPPLIER_MOBILE_LABEL }) {
  return <span className={SUPPLIER_MOBILE_LABEL[variant]}>{children}</span>
}

function AmountCell({ label, children }: { label: string; children: ReactNode }) {
  return (
    <span role="cell" className="flex min-w-0 flex-col gap-0.5 @xl:items-end">
      <SupplierMobileLabel>{label}</SupplierMobileLabel>
      {children}
    </span>
  )
}

/**
 * The product's suppliers read-only (spec 0320): a compact price list — identity, price, cost (or the commission
 * that replaces it) and margin — default first. One structure: on the table's columns from the `@xl` container up,
 * stacked with visible labels below.
 */
export function ProductSuppliersTable({ suppliers }: { suppliers: ProductRecordSupplier[] }) {
  const { t } = useTranslation()
  if (suppliers.length === 0) {
    return <EmptySuppliers />
  }

  return (
    <div role="table" aria-label={t('products.form.suppliers.title')} className="@container min-w-0 overflow-hidden rounded-lg border">
      <div role="row" className="hidden border-b bg-muted/40 px-3 py-1.5 @xl:block">
        <div className={cn(SUPPLIER_TABLE_GRID, 'text-[11px] font-medium tracking-wide text-muted-foreground uppercase')}>
          <span role="columnheader">{t('products.form.suppliers.supplier')}</span>
          <span role="columnheader" className="text-right">{t('products.form.suppliers.price')}</span>
          <span role="columnheader" className="text-right">{t('products.form.suppliers.cost')}</span>
          <span role="columnheader" className="text-right">{t('products.form.suppliers.margin')}</span>
        </div>
      </div>
      {suppliers.map((supplier) => (
        <div key={supplier.supplier_id} role="row" className={cn(SUPPLIER_TABLE_ROW_GRID, 'border-b px-3 py-2 text-sm last:border-b-0')}>
          <span role="cell" className="min-w-0">
            <SupplierIdentity supplier={supplier} />
          </span>
          <div className="grid grid-cols-3 gap-2 @xl:contents">
            <AmountCell label={t('products.form.suppliers.price')}>
              <span className="tabular-nums">{formatEuro(supplier.price)}</span>
            </AmountCell>
            <AmountCell label={t('products.form.suppliers.cost')}>
              <CostValue supplier={supplier} />
            </AmountCell>
            <AmountCell label={t('products.form.suppliers.margin')}>
              <SupplierMarginValue margin={marginOf(supplier)} />
            </AmountCell>
          </div>
        </div>
      ))}
    </div>
  )
}
