import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { BookOpen, ListFilter } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { moduleLabel, sectionLabel } from '@/features/api-integrations/api-module-labels'
import { ModuleIcon } from '@/features/api-integrations/components/module-icon'
import { ApiReferenceFilters } from '@/features/api-integrations/components/api-reference-filters'
import { ALL_RESULTS_VIEW, INTRO_VIEW, type ApiReferenceState } from '@/features/api-integrations/use-api-reference'
import { cn } from '@/lib/utils'

const NAV_BUTTON =
  'flex w-full items-center gap-2 rounded-md px-2 py-1 text-left text-xs transition-colors hover:bg-muted/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-[current=true]:bg-card aria-[current=true]:font-medium aria-[current=true]:shadow-xs'

interface NavButtonProps {
  active: boolean
  onClick: () => void
  icon: ReactNode
  label: string
  count?: number
}

function NavButton({ active, onClick, icon, label, count }: NavButtonProps) {
  return (
    <button type="button" aria-current={active ? 'true' : undefined} onClick={onClick} className={NAV_BUTTON}>
      {icon}
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {count === undefined ? null : <span className="text-[11px] text-muted-foreground tabular-nums">{count}</span>}
    </button>
  )
}

/**
 * Left column: filters, then the module navigation grouped by menu section.
 * Below lg the navigation collapses into a compact module select.
 */
export function ApiReferenceSidebar({ state }: { state: ApiReferenceState }) {
  const { t } = useTranslation()
  const { sections, view, selectView, isFiltering } = state
  const totalMatches = sections.reduce(
    (sum, section) => sum + section.modules.reduce((acc, module) => acc + module.operations.length, 0),
    0,
  )

  return (
    <aside className="flex min-w-0 flex-col gap-3 rounded-lg border border-border bg-surface p-2.5 lg:sticky lg:top-3 lg:max-h-[calc(100vh-1.5rem)] lg:w-64 lg:shrink-0">
      <ApiReferenceFilters
        query={state.query}
        setQuery={state.setQuery}
        methods={state.methods}
        toggleMethod={state.toggleMethod}
        methodCounts={state.methodCounts}
      />

      <div className="lg:hidden">
        <Select value={view} onValueChange={selectView}>
          <SelectTrigger size="sm" aria-label={t('apiIntegrations.docs.moduleSelect')} className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={INTRO_VIEW}>{t('apiIntegrations.docs.intro')}</SelectItem>
            {isFiltering ? (
              <SelectItem value={ALL_RESULTS_VIEW}>
                {t('apiIntegrations.docs.allResults')} ({totalMatches})
              </SelectItem>
            ) : null}
            {sections.map((section) => (
              <SelectGroup key={section.key}>
                <SelectLabel>{sectionLabel(t, section)}</SelectLabel>
                {section.modules.map((module) => (
                  <SelectItem key={module.key} value={module.key}>
                    {moduleLabel(t, module)} ({module.operations.length})
                  </SelectItem>
                ))}
              </SelectGroup>
            ))}
          </SelectContent>
        </Select>
      </div>

      <nav
        aria-label={t('apiIntegrations.docs.navLabel')}
        className="hidden min-h-0 flex-col gap-3 overflow-y-auto lg:flex"
      >
        <div className="flex flex-col gap-0.5">
          {isFiltering ? (
            <NavButton
              active={view === ALL_RESULTS_VIEW}
              onClick={() => selectView(ALL_RESULTS_VIEW)}
              icon={<ListFilter className="size-3.5 shrink-0" aria-hidden />}
              label={t('apiIntegrations.docs.allResults')}
              count={totalMatches}
            />
          ) : (
            <NavButton
              active={view === INTRO_VIEW}
              onClick={() => selectView(INTRO_VIEW)}
              icon={<BookOpen className="size-3.5 shrink-0" aria-hidden />}
              label={t('apiIntegrations.docs.intro')}
            />
          )}
        </div>
        {sections.map((section) => (
          <div key={section.key} role="group" aria-label={sectionLabel(t, section)} className="flex flex-col gap-0.5">
            <p aria-hidden className="px-2 pb-0.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
              {sectionLabel(t, section)}
            </p>
            {section.modules.map((module) => (
              <NavButton
                key={module.key}
                active={view === module.key}
                onClick={() => selectView(module.key)}
                icon={<ModuleIcon module={module} className={cn('size-3.5 shrink-0', view === module.key && 'text-primary')} />}
                label={moduleLabel(t, module)}
                count={module.operations.length}
              />
            ))}
          </div>
        ))}
      </nav>
    </aside>
  )
}
