/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { PurchaseInvoiceDeleteDialog } from '@/features/purchase-invoices/purchase-invoice-delete-dialog'
import { PurchaseInvoiceDetailView } from '@/features/purchase-invoices/purchase-invoice-detail'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import { usePurchaseInvoiceRowActions } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'

const BASE_PATH = `/${PURCHASE_INVOICES_DOMAIN}`

/**
 * Content-only detail of a passive document for the module registry (spec 0042):
 * it is its own edit surface (fields and lines in place, delete dialog here), so the generic hosts neither add an Edit button nor an edit route.
 */
export function PurchaseInvoiceDetailScreen({ id, onChanged, onSaved }: ModuleDetailScreenProps) {
  const navigate = useNavigate()
  const actions = usePurchaseInvoiceRowActions()
  const { openDelete } = actions

  const handleDeleted = () => {
    onChanged?.()
    // The record is gone: the sheet closes (modal) or the page goes back to the list.
    if (onSaved) {
      onSaved()
    } else {
      void navigate(BASE_PATH)
    }
  }

  return (
    <>
      <PurchaseInvoiceDetailView
        invoiceId={id}
        onDelete={(numberLabel) => openDelete({ id, numberLabel })}
        onChanged={onChanged}
      />
      <PurchaseInvoiceDeleteDialog target={actions.deleteTarget} onClose={actions.closeDelete} onDeleted={handleDeleted} />
    </>
  )
}

/**
 * Deep-link create host: the editor is a dialog, so the screen only mounts it open and reports save/cancel.
 * No edit route is generated: a saved document is edited on its record (spec 0263).
 */
export function PurchaseInvoiceFormScreen({ onSuccess, onCancel }: ModuleFormScreenProps) {
  const saved = useRef(false)

  return (
    <PurchaseInvoiceEditorDialog
      open
      onOpenChange={(open) => {
        if (!open && !saved.current) {
          onCancel()
        }
      }}
      onSaved={(invoice) => {
        saved.current = true
        onSuccess(invoice.id)
      }}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: PURCHASE_INVOICES_DOMAIN,
  basePath: BASE_PATH,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseInvoices',
  generateEditRoute: false,
  detailOwnsEditAction: true,
  DetailScreen: PurchaseInvoiceDetailScreen,
  FormScreen: PurchaseInvoiceFormScreen,
}
