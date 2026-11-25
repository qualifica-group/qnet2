/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { WorkOrderDetailScreen, WorkOrderFormScreen } from '@/features/work-orders/work-order-screens'
import type { ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'

/**
 * The "Commesse di Progetto" screens: the same detail as "Commesse" (a
 * project work order is the same record, module registry spec 0042), and a
 * create screen of its own, a project work order born without an offer (spec
 * 0431). One from an offer is still created from the offer's contract.
 */
function ProjectWorkOrderFormScreen(props: ModuleFormScreenProps) {
  return <WorkOrderFormScreen {...props} standalone />
}

export const moduleScreens: ModuleScreens = {
  DetailScreen: WorkOrderDetailScreen,
  FormScreen: ProjectWorkOrderFormScreen,
}
