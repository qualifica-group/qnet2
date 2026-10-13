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
import { PurchaseInvoiceActionDialogs } from '@/features/purchase-invoices/purchase-invoice-action-dialogs'
import { PurchaseInvoiceDetailView } from '@/features/purchase-invoices/purchase-invoice-detail'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import { usePurchaseInvoiceRowActions } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'

const BASE_PATH = `/${PURCHASE_INVOICES_DOMAIN}`

/**
 * Content-only detail of a passive document for the module registry (spec 0042):
 * it is its own edit surface (edit / supplier details / delete open dialogs
 * here), so the generic hosts neither add an Edit button nor an edit route.
 */
export function PurchaseInvoiceDetailScreen({ id, onChanged, onSaved }: ModuleDetailScreenProps) {
  const navigate = useNavigate()
  const actions = usePurchaseInvoiceRowActions()
  const { openEdit, openDetails, openDelete } = actions

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
        onEdit={() => openEdit(id)}
        onDetails={() => openDetails(id)}
        onDelete={(numberLabel) => openDelete({ id, numberLabel })}
      />
      <PurchaseInvoiceActionDialogs
        editId={actions.editId}
        detailsId={actions.detailsId}
        deleteTarget={actions.deleteTarget}
        onCloseEdit={actions.closeEdit}
        onCloseDetails={actions.closeDetails}
        onCloseDelete={actions.closeDelete}
        onSaved={() => {
          actions.closeEdit()
          onChanged?.()
        }}
        onDeleted={handleDeleted}
      />
    </>
  )
}

/** Deep-link form host: the editor is a dialog, so the screen only mounts it open and reports save/cancel. */
export function PurchaseInvoiceFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const saved = useRef(false)

  return (
    <PurchaseInvoiceEditorDialog
      {...(mode.type === 'edit' ? { mode: 'edit' as const, invoiceId: mode.id } : { mode: 'create' as const })}
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
