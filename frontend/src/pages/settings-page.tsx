import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Cog, Lock, UserRound, type LucideIcon } from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { useAuth } from '@/features/auth/use-auth'
import { ProfileForm } from '@/features/auth/profile-form'
import { PasswordForm } from '@/features/auth/password-form'
import { AvatarForm } from '@/features/auth/avatar-form'
import { ModuleOpenModeForm } from '@/features/modules/module-open-mode-form'
import { UiScaleForm } from '@/features/appearance/ui-scale-form'
import { DateFormatForm } from '@/features/appearance/date-format-form'

interface SubSectionMeta {
  id: string
  titleKey: string
}

interface SectionMeta {
  id: string
  icon: LucideIcon
  titleKey: string
  descKey: string
  // Sub-sections rendered as separate cards inside this section's panel; the
  // rail lists them as children that scroll to their card.
  children?: readonly SubSectionMeta[]
}

// Section identity shared by the rail and the panel; the array order is the rail
// order and the first entry is the initial selection.
const SECTIONS: readonly SectionMeta[] = [
  {
    id: 'profile',
    icon: UserRound,
    titleKey: 'settings.profileTitle',
    descKey: 'settings.profileSubtitle',
  },
  {
    id: 'security',
    icon: Lock,
    titleKey: 'settings.passwordTitle',
    descKey: 'settings.passwordSubtitle',
  },
  {
    id: 'system',
    icon: Cog,
    titleKey: 'settings.systemSettings.title',
    descKey: 'settings.systemSettings.subtitle',
    children: [
      { id: 'module-open', titleKey: 'settings.moduleOpenMode.title' },
      { id: 'ui-scale', titleKey: 'settings.uiScale.title' },
      { id: 'date-format', titleKey: 'settings.dateFormat.title' },
    ],
  },
]

/**
 * Connected-user settings. Two-column on desktop: a sticky identity + section
 * rail beside a single panel that shows only the selected section (the others
 * are hidden), collapsing to a single column below lg. A section with children
 * renders one card per child and the rail lists those children as scroll links.
 */
export default function SettingsPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const [activeSection, setActiveSection] = useState<string>(
    SECTIONS[0]?.id ?? '',
  )

  const active =
    SECTIONS.find((section) => section.id === activeSection) ?? SECTIONS[0]

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('settings.title')}
        </h1>
        <p className="text-sm text-muted-foreground">{t('settings.subtitle')}</p>
      </header>

      <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside className="flex flex-col gap-4 lg:sticky lg:top-6 lg:self-start">
          {user && (
            <Card className="items-center gap-3 py-5 text-center">
              <UserAvatar
                name={user.name}
                src={user.avatar_url}
                className="size-16"
              />
              <div className="flex min-w-0 flex-col px-4">
                <span className="truncate font-semibold leading-tight">
                  {user.name}
                </span>
                <span className="truncate text-sm text-muted-foreground">
                  {user.email}
                </span>
              </div>
            </Card>
          )}

          <nav
            aria-label={t('settings.sectionNavLabel')}
            className="flex flex-col gap-1"
          >
            {SECTIONS.map((section) => {
              const Icon = section.icon
              const isActive = active?.id === section.id
              return (
                <div key={section.id} className="flex flex-col gap-0.5">
                  <button
                    type="button"
                    onClick={() => setActiveSection(section.id)}
                    aria-current={isActive ? 'page' : undefined}
                    className={cn(
                      'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                      isActive
                        ? 'bg-accent text-accent-foreground'
                        : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    )}
                  >
                    <Icon className="size-4 shrink-0" aria-hidden="true" />
                    {t(section.titleKey)}
                  </button>

                  {isActive && section.children && (
                    <div className="ml-7 flex flex-col gap-0.5 border-l pl-2">
                      {section.children.map((child) => (
                        <button
                          key={child.id}
                          type="button"
                          onClick={() => scrollToSection(child.id)}
                          className="rounded-md px-2.5 py-1 text-left text-xs font-medium text-muted-foreground transition-colors hover:bg-accent/50 hover:text-foreground"
                        >
                          {t(child.titleKey)}
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
          </nav>
        </aside>

        <div className="flex min-w-0 flex-col gap-6">
          {active?.children
            ? active.children.map((child) => (
                <Card key={child.id} id={child.id} className="scroll-mt-6">
                  <CardContent>
                    <FieldPanel>{renderSubSection(child.id)}</FieldPanel>
                  </CardContent>
                </Card>
              ))
            : active && (
                <SettingsSection
                  icon={active.icon}
                  title={t(active.titleKey)}
                  description={t(active.descKey)}
                >
                  {active.id === 'profile' && (
                    <>
                      <AvatarForm />
                      <Separator />
                      <FieldPanel>
                        <ProfileForm />
                      </FieldPanel>
                    </>
                  )}

                  {active.id === 'security' && (
                    <FieldPanel>
                      <PasswordForm />
                    </FieldPanel>
                  )}
                </SettingsSection>
              )}
        </div>
      </div>
    </div>
  )
}

/** Maps a system sub-section id to its form. */
function renderSubSection(id: string): ReactNode {
  switch (id) {
    case 'module-open':
      return <ModuleOpenModeForm />
    case 'ui-scale':
      return <UiScaleForm />
    case 'date-format':
      return <DateFormatForm />
    default:
      return null
  }
}

interface SettingsSectionProps {
  icon: LucideIcon
  title: string
  description: string
  children: ReactNode
}

/** White section card with an icon-led header and a stacked body. */
function SettingsSection({
  icon: Icon,
  title,
  description,
  children,
}: SettingsSectionProps) {
  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Icon className="size-4" aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-0.5">
            <CardTitle className="text-base">{title}</CardTitle>
            <CardDescription>{description}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">{children}</CardContent>
    </Card>
  )
}

/**
 * Muted panel that lifts the enclosed form fields onto a white surface: it forces
 * the design-system Input/Select triggers to render solid `bg-card` so they read
 * as distinct, elevated fields against the tinted panel (settings-only styling,
 * scoped via the data-slot descendant selectors — checkboxes/file inputs are
 * untouched because they carry no data-slot).
 */
function FieldPanel({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-lg border bg-muted/50 p-4 sm:p-5 [&_[data-slot=input]]:bg-card [&_[data-slot=select-trigger]]:bg-card">
      {children}
    </div>
  )
}

/** Smooth-scrolls to a sub-section card, honoring reduced-motion. */
function scrollToSection(id: string): void {
  const element = document.getElementById(id)
  if (!element) {
    return
  }
  const reduceMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)',
  ).matches
  element.scrollIntoView({
    behavior: reduceMotion ? 'auto' : 'smooth',
    block: 'start',
  })
}
