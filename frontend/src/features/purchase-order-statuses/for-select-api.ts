import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import { PURCHASE_ORDER_STATUSES_RESOURCE } from '@/features/purchase-order-statuses/types'

/** The catalog is 7 rows: one page of the for-select (capped at 100 server-side) covers it. */
const CATALOG_PAGE_SIZE = 100

/** Presentation bag projected next to `{id, label}` by `GET /api/purchase-order-statuses/for-select`. */
interface PurchaseOrderStatusForSelectItem extends ForSelectItem {
  meta: { system_key: string; color: string; icon: string | null; sort_order: number }
}

/** One ODA status as the UI draws it: `systemKey` is the enum value the documents carry in `status`. */
export interface PurchaseOrderStatusCatalogEntry {
  id: number
  systemKey: string
  name: string
  color: string
  icon: string | null
}

/** Fetches every ODA status in catalog order (`sort_order`), with its current name, color and icon. */
export async function fetchPurchaseOrderStatusCatalog(): Promise<PurchaseOrderStatusCatalogEntry[]> {
  const response = await fetchForSelect(PURCHASE_ORDER_STATUSES_RESOURCE, { limit: CATALOG_PAGE_SIZE })
  return (response.items as PurchaseOrderStatusForSelectItem[]).map((item) => ({
    id: item.id,
    systemKey: item.meta.system_key,
    name: item.label,
    color: item.meta.color,
    icon: item.meta.icon,
  }))
}
