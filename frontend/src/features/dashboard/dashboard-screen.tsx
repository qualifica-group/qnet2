/**
 * `/dashboard` page, intentionally empty (user decision 2026-11-09): the blocks are
 * not mounted so the home issues no data request, because their aggregates
 * saturated production at peak time.
 */
export function DashboardScreen() {
  return <div className="flex flex-1 flex-col gap-6" />
}
