import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/** Cellule de chiffre : police condensée, alignée à droite, resserrée sous 768 px. */
const FIGURE = 'text-right font-heading text-base font-semibold max-md:px-1.5';

/**
 * Brut de chaque joueur dans l'ordre de la carte (spec `rounds`, « Bruts d'un
 * round »), aux classes du classement net de Next, qui le remplacera avec
 * handicap-index. Deux colonnes : tient à 320 px, le nom passe à la ligne
 * plutôt que d'élargir la table.
 */
export function RoundGross({
    players,
}: {
    players: { name: string; gross: number }[];
}) {
    return (
        <Table aria-label="Bruts">
            <TableHeader className="bg-band">
                <TableRow className="hover:bg-transparent">
                    <TableHead className="text-band-ink max-md:px-1.5">
                        Joueur
                    </TableHead>
                    <TableHead className="text-right text-band-ink max-md:px-1.5">
                        Brut
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {players.map((player, position) => (
                    <TableRow key={position}>
                        <TableCell className="font-heading text-lg font-semibold whitespace-normal max-md:px-1.5">
                            {player.name}
                        </TableCell>
                        <TableCell className={FIGURE}>{player.gross}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
