import { Button as ButtonPrimitive } from "@base-ui/react/button"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"

// Boutons du design (D5) : pleine encre par défaut, contour `rule`, rouge
// plein à texte blanc `on-signal` (exception de contraste, D9). Hauteurs 48 px
// (défaut), 44 (`sm`, boutons de liste), 52 (`lg`, feuille) et 56 px (`xl`,
// bouton principal collé en bas), à toute largeur. Bloqué : fond `surface-3`,
// texte `ink-3` (contrôle désactivé, exempté par WCAG 1.4.3, DO15).
const buttonVariants = cva(
  "group/button inline-flex shrink-0 items-center justify-center gap-1.5 border border-transparent bg-clip-padding whitespace-nowrap transition-[color,background-color,border-color,scale] duration-(--dur-press) ease-out outline-none select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-ring disabled:cursor-not-allowed disabled:border-transparent disabled:bg-surface-3 disabled:text-ink-3 aria-invalid:border-destructive [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: "bg-ink text-bg hover:not-disabled:bg-ink/90",
        outline:
          "border-rule bg-transparent text-ink hover:not-disabled:bg-surface-2 aria-expanded:bg-surface-2",
        secondary: "bg-surface-3 text-ink hover:not-disabled:bg-surface-2",
        ghost:
          "text-ink-2 hover:not-disabled:bg-surface-2 hover:not-disabled:text-ink aria-expanded:bg-surface-2 aria-expanded:text-ink",
        destructive: "bg-signal text-on-signal hover:not-disabled:bg-signal/90",
        link: "text-ink underline decoration-1 underline-offset-4 hover:not-disabled:decoration-2",
      },
      size: {
        default: "h-12 rounded-10 px-[18px] text-label",
        xs: "h-11 rounded-10 px-3 text-link-13",
        sm: "h-11 rounded-10 px-3.5 text-label-14",
        lg: "h-13 rounded-12 px-5 text-label",
        xl: "h-14 rounded-14 px-5 text-body-l font-semibold",
        icon: "size-11 rounded-10",
        "icon-xs": "size-11 rounded-10",
        "icon-sm": "size-11 rounded-10",
        "icon-lg": "size-12 rounded-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant = "default",
  size = "default",
  ...props
}: ButtonPrimitive.Props & VariantProps<typeof buttonVariants>) {
  return (
    <ButtonPrimitive
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
