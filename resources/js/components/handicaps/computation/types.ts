/**
 * Un des 20 derniers rounds d'un joueur, tel que PlayerIndexComputation
 * l'envoie (design D2 d'index-computation-page). Valeurs en dixièmes ;
 * `ratingT` et `slope` sont null quand le round n'a pas de rating confirmée,
 * le calcul a alors pris le par du round et 113.
 */
export type ComputationRound = {
    roundId: string;
    playedOn: string;
    courseName: string;
    gross: number;
    adjusted: number;
    parTotal: number;
    ratingT: number | null;
    slope: number | null;
    differentialT: number;
    countsInCurrent: boolean;
};

/** Le dernier trou au-dessus de par + 4 parmi les 20 derniers rounds. */
export type CappedHole = { hole: number; par: number; strokes: number };

/** Les données du calcul d'un joueur (design D2) : jamais recalculées par la page. */
export type IndexComputation = {
    /** 20 au plus, du plus récent au plus ancien, dans l'ordre de l'index. */
    rounds: ComputationRound[];
    cappedHole: CappedHole | null;
    /** Moyenne des différentiels retenus, en dixièmes ; null sans round. */
    meanT: number | null;
    /** −20, −10 ou 0 dixième ; 0 sans round et pour un index provisoire. */
    adjustmentT: number;
};
