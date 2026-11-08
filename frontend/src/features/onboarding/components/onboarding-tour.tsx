import { useEffect, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Dialog as DialogPrimitive } from 'radix-ui'
import { ArrowLeft, ArrowRight, CircleCheck, Compass } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import {
  centeredPosition,
  computeCardPosition,
  spotlightBox,
  type Box,
  type CardPosition,
  type Size,
} from '@/features/onboarding/card-position'
import { FINISH_STEP_ID } from '@/features/onboarding/steps'
import type { TourMode, TourStep } from '@/features/onboarding/types'
import { TourProgress } from '@/features/onboarding/components/tour-progress'

interface OnboardingTourProps {
  step: TourStep
  mode: TourMode
  userName: string
  /** Position among the steps with a target; 0 on the intro/outro cards. */
  progress: { current: number; total: number }
  isFirst: boolean
  isLast: boolean
  target: Box | null
  viewport: Size
  onNext: () => void
  onBack: () => void
  onSkip: () => void
  onFinish: () => void
}

/**
 * The tour layer: a spotlight cut out of a dimmed screen around the current
 * target and a card beside it (spec 0368 D-3). A modal Radix dialog gives the
 * focus trap, the blocked outside clicks and the stacking above the phone menu
 * drawer; Esc and outside clicks never close a mandatory tour (D-9).
 */
export function OnboardingTour({
  step,
  mode,
  userName,
  progress,
  isFirst,
  isLast,
  target,
  viewport,
  onNext,
  onBack,
  onSkip,
  onFinish,
}: OnboardingTourProps) {
  const { t } = useTranslation('onboarding')
  const cardRef = useRef<HTMLDivElement>(null)
  const primaryRef = useRef<HTMLButtonElement>(null)
  const [cardSize, setCardSize] = useState<Size | null>(null)

  const spotlight = step.surface !== 'center' && target ? spotlightBox(target) : null
  const position = cardPosition(step, target, cardSize, viewport)
  const skippable = mode === 'skippable'
  const isWelcome = isFirst && step.surface === 'center'
  const isFinish = step.id === FINISH_STEP_ID

  // The card is measured before paint, so it is placed beside the target on the first frame.
  useLayoutEffect(() => {
    const card = cardRef.current
    if (card) {
      setCardSize({ width: card.offsetWidth, height: card.offsetHeight })
    }
  }, [step.id, viewport.width, viewport.height])

  // Keyboard users land on the main action of every step.
  useEffect(() => {
    primaryRef.current?.focus()
  }, [step.id])

  const primaryAction = isLast ? onFinish : onNext

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'ArrowRight' && !isLast) {
      event.preventDefault()
      onNext()
    } else if (event.key === 'ArrowLeft' && !isFirst) {
      event.preventDefault()
      onBack()
    }
  }

  return (
    <DialogPrimitive.Root open modal>
      <DialogPrimitive.Portal>
        {spotlight ? (
          <div
            aria-hidden
            data-slot="onboarding-spotlight"
            className="pointer-events-none fixed z-[60] rounded-lg shadow-[0_0_0_9999px_rgb(0_0_0/0.55)] ring-2 ring-primary motion-safe:transition-all motion-safe:duration-300"
            style={{ top: spotlight.top, left: spotlight.left, width: spotlight.width, height: spotlight.height }}
          />
        ) : (
          <div aria-hidden className="pointer-events-none fixed inset-0 z-[60] bg-black/55" />
        )}
        <DialogPrimitive.Content
          ref={cardRef}
          aria-modal="true"
          onKeyDown={onKeyDown}
          onOpenAutoFocus={(event) => {
            event.preventDefault()
            primaryRef.current?.focus()
          }}
          onEscapeKeyDown={(event) => {
            event.preventDefault()
            if (skippable) {
              onSkip()
            }
          }}
          onPointerDownOutside={(event) => event.preventDefault()}
          onInteractOutside={(event) => event.preventDefault()}
          className={cn(
            'fixed z-[60] flex w-[min(22rem,calc(100vw-1.5rem))] flex-col gap-3 rounded-lg border border-border bg-popover p-4 text-popover-foreground shadow-lg outline-none',
            'motion-safe:transition-[top,left] motion-safe:duration-300',
            position === null && 'invisible',
          )}
          style={position ? { top: position.top, left: position.left } : undefined}
        >
          {step.surface === 'center' ? (
            <span className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
              {isFinish ? <CircleCheck className="size-5" /> : <Compass className="size-5" />}
            </span>
          ) : (
            <TourProgress current={progress.current} total={progress.total} />
          )}

          <div className="flex flex-col gap-1">
            <DialogPrimitive.Title className="text-base font-semibold leading-tight">
              {t(`steps.${step.id}.title`, { name: userName })}
            </DialogPrimitive.Title>
            <DialogPrimitive.Description className="text-sm text-muted-foreground">
              {t(`steps.${step.id}.body`)}
            </DialogPrimitive.Description>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-2">
            {skippable && !isLast ? (
              <Button type="button" variant="link" size="sm" className="px-0 text-muted-foreground" onClick={onSkip}>
                {t('tour.skip')}
              </Button>
            ) : (
              <span />
            )}
            <div className="ml-auto flex items-center gap-2">
              {isFirst ? null : (
                <Button type="button" variant="secondary" size="sm" onClick={onBack}>
                  <ArrowLeft />
                  {t('tour.back')}
                </Button>
              )}
              <Button ref={primaryRef} type="button" size="sm" onClick={primaryAction}>
                {primaryLabel(t, isWelcome, isLast)}
                {isLast ? null : <ArrowRight />}
              </Button>
            </div>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}

function cardPosition(step: TourStep, target: Box | null, card: Size | null, viewport: Size): CardPosition | null {
  if (card === null) {
    return null
  }
  // A step whose target left the screen still reads fine as a centred card.
  if (step.surface === 'center' || target === null) {
    return centeredPosition(card, viewport)
  }
  return computeCardPosition(target, card, viewport, step.placement)
}

function primaryLabel(t: (key: string) => string, isWelcome: boolean, isLast: boolean): string {
  if (isLast) {
    return t('tour.finish')
  }
  return isWelcome ? t('tour.start') : t('tour.next')
}
