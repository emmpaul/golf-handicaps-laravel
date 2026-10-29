import { mergeProps } from "@base-ui/react/merge-props"
import { useRender } from "@base-ui/react/use-render"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"

// Pastille d'état du design (spec visual-theme « Pastilles d'état », D5) : Geist Mono 500,
// 10 px, capitales, rayon 999 px, texte de la couleur de l'état sur cette couleur à 15 %. Un état
// neutre (`default`, `secondary`, `outline`) prend le texte `ink-2` sur `surface-3`. La casse du
// DOM reste celle du texte passé (D2).
const badgeVariants = cva(
  "group/badge inline-flex h-5 w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-pill px-2 font-mono text-caption whitespace-nowrap uppercase focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-ring has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&>svg]:pointer-events-none [&>svg]:size-3!",
  {
    variants: {
      variant: {
        default: "bg-surface-3 text-ink-2",
        secondary: "bg-surface-3 text-ink-2",
        outline: "bg-surface-3 text-ink-2",
        accent: "bg-accent/15 text-accent",
        destructive: "bg-signal/15 text-signal",
        info: "bg-info/15 text-info",
        ok: "bg-ok/15 text-ok",
        ghost: "text-ink-2",
        link: "px-0 text-ink underline underline-offset-4",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant = "default",
  render,
  ...props
}: useRender.ComponentProps<"span"> & VariantProps<typeof badgeVariants>) {
  return useRender({
    defaultTagName: "span",
    props: mergeProps<"span">(
      {
        className: cn(badgeVariants({ variant }), className),
      },
      props
    ),
    render,
    state: {
      slot: "badge",
      variant,
    },
  })
}

export { Badge, badgeVariants }
