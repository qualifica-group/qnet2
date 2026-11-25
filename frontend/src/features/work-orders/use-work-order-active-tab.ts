import { useState } from 'react'
import { useLocation, useSearchParams } from 'react-router-dom'
import { WORK_ORDER_INFO_TAB, type WorkOrderDetailTab } from '@/features/work-orders/use-work-order-detail-tabs'
import { workOrderPath } from '@/features/work-orders/work-order-type-routing'

/**
 * The work order detail's open tab, behaving like a page (user directive
 * 2026-11-16): on the work order's own page the tab lives in the URL
 * (`?tab=tasks`), so a reload stays on it while a fresh visit (the list's
 * "eye", a link) opens Info. Info itself drops the param. Shown anywhere
 * else (the modal Sheet over a list) the tab is plain local state, starting
 * on `initialTab` (the grid menu's Storico Note / Task / Documenti, spec
 * 0405) or Info. A tab inside a group (Documenti) opens its group; a tab the
 * actor cannot see falls back to Info.
 */

const TAB_PARAM = 'tab'

type TabNode = Pick<WorkOrderDetailTab, 'value' | 'subTabs'>

/** The top-level tab showing `requested`: itself, or the group holding it. */
function topLevelTabFor(tabs: TabNode[], requested: string | null): string | null {
  if (requested === null) {
    return null
  }
  const owner = tabs.find(
    (tab) => tab.value === requested || tab.subTabs?.some((subTab) => subTab.value === requested) === true,
  )
  return owner?.value ?? null
}

/** The work order's page in either module, "Commesse" or "Commesse di Progetto" (2026-11-25). */
function isOwnDetailPage(pathname: string, workOrderId: number): boolean {
  const path = pathname.replace(/\/+$/, '')
  return path === workOrderPath('processing', workOrderId) || path === workOrderPath('project', workOrderId)
}

export function useWorkOrderActiveTab(workOrderId: number, tabs: TabNode[], initialTab?: string) {
  const { pathname } = useLocation()
  const [searchParams, setSearchParams] = useSearchParams()
  const [localTab, setLocalTab] = useState<{ workOrderId: number; tab: string } | null>(null)
  const isOnPage = isOwnDetailPage(pathname, workOrderId)

  const requestedTab = isOnPage
    ? searchParams.get(TAB_PARAM)
    : localTab?.workOrderId === workOrderId
      ? localTab.tab
      : (initialTab ?? null)
  const activeTab = topLevelTabFor(tabs, requestedTab) ?? WORK_ORDER_INFO_TAB

  const setActiveTab = (tab: string) => {
    if (!isOnPage) {
      setLocalTab({ workOrderId, tab })
      return
    }
    // `replace`: switching tabs is not a navigation step the Back button should replay.
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        if (tab === WORK_ORDER_INFO_TAB) {
          next.delete(TAB_PARAM)
        } else {
          next.set(TAB_PARAM, tab)
        }
        return next
      },
      { replace: true },
    )
  }

  return { activeTab, setActiveTab }
}
