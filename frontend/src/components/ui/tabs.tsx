import * as React from "react"
import { Tabs as TabsPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

function Tabs({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.Root>) {
  return (
    <TabsPrimitive.Root
      data-slot="tabs"
      className={cn("flex flex-col gap-2", className)}
      {...props}
    />
  )
}

/**
 * The one tab look of the app (decisione utente 2026-10-26: "i tab devono
 * essere tutti uguali"): a compact surface trough with the active tab raised
 * as a card chip. Exported for the few strips that are not Radix tabs
 * (`FilterTabs`, the request-management overflow trough and "more" button), so
 * they read exactly like `TabsList`/`TabsTrigger`. Callers pass layout only
 * (width, wrapping), never their own sizing or colours.
 *
 * The strip hosts the active chip, so it takes the scale's intermediate
 * surface (rung 2): the page body (rung 1) and the card (rung 3) both read
 * against it, and the active `--card` chip is the only raised element in it.
 */
export const TABS_LIST_CLASS =
  "inline-flex h-auto w-full flex-wrap items-center justify-start gap-1 rounded-lg border border-border/60 bg-surface p-1 text-muted-foreground shadow-sm"

export const TABS_TRIGGER_CLASS =
  "inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium whitespace-nowrap text-muted-foreground outline-none transition-all duration-200 " +
  "hover:bg-card/60 hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 " +
  "data-[state=active]:bg-card data-[state=active]:text-primary data-[state=active]:shadow-sm data-[state=active]:ring-1 data-[state=active]:ring-primary/15 " +
  "[&_svg]:pointer-events-none [&_svg]:size-3.5 [&_svg]:shrink-0 [&_svg]:transition-transform [&_svg]:duration-200 data-[state=active]:[&_svg]:scale-110"

function TabsList({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.List>) {
  return (
    <TabsPrimitive.List
      data-slot="tabs-list"
      className={cn(TABS_LIST_CLASS, className)}
      {...props}
    />
  )
}

function TabsTrigger({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.Trigger>) {
  return (
    <TabsPrimitive.Trigger
      data-slot="tabs-trigger"
      className={cn(TABS_TRIGGER_CLASS, className)}
      {...props}
    />
  )
}

function TabsContent({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.Content>) {
  return (
    <TabsPrimitive.Content
      data-slot="tabs-content"
      className={cn("flex-1 outline-none", className)}
      {...props}
    />
  )
}

export { Tabs, TabsList, TabsTrigger, TabsContent }
