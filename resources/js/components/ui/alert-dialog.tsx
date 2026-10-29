"use client"

import * as React from "react"
import { Dialog as DialogPrimitive } from "@base-ui/react/dialog"
import { cn } from "@/lib/utils"

import { Button } from "@/components/ui/button"

// Feuille de confirmation du design (spec app-shell « Feuille de confirmation », D5) : même API
// que l'alert dialog de shadcn, rendue en feuille du bas sur le voile `scrim`. Bâtie sur le Dialog
// de base-ui avec `role="alertdialog"` : l'AlertDialog de base-ui refuse la fermeture par le
// voile, que le design demande. Focus piégé, Échap, focus rendu au déclencheur : base-ui.

function AlertDialog({ ...props }: DialogPrimitive.Root.Props) {
  return <DialogPrimitive.Root data-slot="alert-dialog" {...props} />
}

function AlertDialogTrigger({ ...props }: DialogPrimitive.Trigger.Props) {
  return <DialogPrimitive.Trigger data-slot="alert-dialog-trigger" {...props} />
}

function AlertDialogPortal({ ...props }: DialogPrimitive.Portal.Props) {
  return <DialogPrimitive.Portal data-slot="alert-dialog-portal" {...props} />
}

function AlertDialogOverlay({
  className,
  ...props
}: DialogPrimitive.Backdrop.Props) {
  return (
    <DialogPrimitive.Backdrop
      data-slot="alert-dialog-overlay"
      className={cn(
        // Voile `scrim` (noir à 60 %), fondu de 300 ms ; mouvement réduit : base.css.
        "fixed inset-0 isolate z-(--z-modal) bg-scrim opacity-100 transition-opacity duration-(--dur-swap) ease-[ease] data-ending-style:opacity-0 data-starting-style:opacity-0",
        className
      )}
      {...props}
    />
  )
}

function AlertDialogContent({
  className,
  ...props
}: DialogPrimitive.Popup.Props) {
  return (
    <AlertDialogPortal>
      <AlertDialogOverlay />
      <DialogPrimitive.Popup
        data-slot="alert-dialog-content"
        role="alertdialog"
        className={cn(
          // Monte du bas de l'écran en 400 ms dans la colonne de 390 px ; coins du haut de 22 px,
          // marges 24 px 20 px 34 px plus la zone sûre du bas.
          "fixed inset-x-0 bottom-0 z-(--z-modal) mx-auto flex max-w-(--column) translate-y-0 flex-col gap-2.5 rounded-t-22 border-t border-rule bg-surface-1 px-5 pt-6 pb-[calc(34px+var(--safe-bottom))] text-ink outline-none transition-[translate] duration-(--dur-sheet) ease-design data-ending-style:translate-y-full data-starting-style:translate-y-full",
          className
        )}
        {...props}
      />
    </AlertDialogPortal>
  )
}

function AlertDialogHeader({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-dialog-header"
      className={cn("flex flex-col gap-2.5", className)}
      {...props}
    />
  )
}

function AlertDialogFooter({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-dialog-footer"
      className={cn("flex flex-col gap-2.5", className)}
      {...props}
    />
  )
}

function AlertDialogMedia({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-dialog-media"
      className={cn("inline-flex size-10 items-center justify-center", className)}
      {...props}
    />
  )
}

function AlertDialogTitle({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Title>) {
  return (
    <DialogPrimitive.Title
      data-slot="alert-dialog-title"
      className={cn("text-name-m leading-[1.2] tracking-normal", className)}
      {...props}
    />
  )
}

function AlertDialogDescription({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Description>) {
  return (
    <DialogPrimitive.Description
      data-slot="alert-dialog-description"
      className={cn("mb-2 text-body text-ink-2", className)}
      {...props}
    />
  )
}

function AlertDialogAction({
  className,
  size = "lg",
  ...props
}: React.ComponentProps<typeof Button>) {
  return (
    <Button
      data-slot="alert-dialog-action"
      size={size}
      className={cn(className)}
      {...props}
    />
  )
}

function AlertDialogCancel({
  className,
  variant = "outline",
  size = "lg",
  ...props
}: DialogPrimitive.Close.Props &
  Pick<React.ComponentProps<typeof Button>, "variant" | "size">) {
  return (
    <DialogPrimitive.Close
      data-slot="alert-dialog-cancel"
      className={cn(className)}
      render={<Button variant={variant} size={size} />}
      {...props}
    />
  )
}

export {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogTitle,
  AlertDialogTrigger,
}
