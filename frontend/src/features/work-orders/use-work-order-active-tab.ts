import { useState } from 'react'
import { useLocation, useSearchParams } from 'react-router-dom'
import { moduleDefinition } from '@/features/work-orders/work-order-module'
import { WORK_ORDER_INFO_TAB } from '@/features/work-orders/use-work-order-detail-tabs'

/**
 * The work order detail's open tab, behaving like a page (user directive
 * 2026-11-16): on the work order's own page the tab lives in the URL
 * (`?tab=tasks`), so a reload stays on it while a fresh visit (the list's
 * "eye", a link) opens Info. Info itself drops the param. Shown anywhere
 * else (the modal Sheet over a list) the tab is plain local state, starting
 * on Info. A tab the actor cannot see falls back to Info.
 */

const TAB_PARAM = 'tab'

function isOwnDetailPage(pathname: string, workOrderId: number): boolean {
  return pathname.replace(/\/+$/, '') === `${moduleDefinition.basePath}/${workOrderId}`
}

export function useWorkOrderActiveTab(workOrderId: number, availableTabs: string[]) {
  const { pathname } = useLocation()
  const [searchParams, setSearchParams] = useSearchParams()
  const [localTab, setLocalTab] = useState<{ workOrderId: number; tab: string } | null>(null)
  const isOnPage = isOwnDetailPage(pathname, workOrderId)

  const requestedTab = isOnPage
    ? searchParams.get(TAB_PARAM)
    : localTab?.workOrderId === workOrderId
      ? localTab.tab
      : null
  const activeTab = requestedTab !== null && availableTabs.includes(requestedTab) ? requestedTab : WORK_ORDER_INFO_TAB

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
