import { SectionLabel } from '@/components/section-label';
import { formatTenths } from '@/lib/format';
import { useEntered } from '@/lib/motion';
import { cn } from '@/lib/utils';

/** Teinte d'un écart par par, décidée par le serveur avant arrondi (D13). */
export type ScoreTint = 'good' | 'neutral' | 'bad';

/** Le bloc STATS d'un joueur tel que `PlayerScoringStats` l'envoie (D13). */
export type ScoringStatsData = {
    rounds: number;
    holes: number;
    averageGrossT: number;
    bestGross: number;
    parOrBetterPercent: number;
    /** Eagle ou mieux, birdies, pars, bogeys, double bogey +. */
    classes: number[];
    classPercents: number[];
    pars: {
        par: number;
        holes: number;
        averageT: number;
        deltaT: number;
        tint: ScoreTint;
    }[];
};

/** Classe de chaque teinte : `ok`, `ink-2`, `signal` (spec « Moyennes par par »). */
const TINT_TEXT: Record<ScoreTint, string> = {
    good: 'text-ok',
    neutral: 'text-ink-2',
    bad: 'text-signal',
};

/** Les cinq classes d'écart, dans l'ordre de `classes`, avec segment et marque. */
const CLASSES = [
    { name: 'Eagle ou mieux', segment: 'bg-signal', mark: 'eagle' },
    { name: 'Birdies', segment: 'bg-signal/55', mark: 'birdie' },
    { name: 'Pars', segment: 'bg-ink/85', mark: 'par' },
    { name: 'Bogeys', segment: 'bg-over/70', mark: 'bogey' },
    { name: 'Double bogey +', segment: 'bg-ink-4', mark: 'double' },
] as const;

type ClassMark = (typeof CLASSES)[number]['mark'];

/** « +0,3 », « +0,0 », « −0,7 » (signe moins U+2212). */
const signedTenths = (tenths: number) =>
    `${tenths < 0 ? '−' : '+'}${formatTenths(Math.abs(tenths))}`;

/** Part d'une classe en largeur de segment : « 16.7% », « 0% ». */
const share = (count: number, holes: number) =>
    `${Number(((count / holes) * 100).toFixed(1))}%`;

/**
 * Bloc STATS de la page Handicaps (spec handicap-index « Statistiques de jeu
 * d'un joueur », « Répartition des trous », « Moyennes par par », « Mise en
 * page du bloc STATS » ; D13) : trois valeurs, la barre des proportions, une
 * ligne par classe (« Eagle ou mieux » seulement s'il compte un trou), puis
 * une case par par joué. Le serveur arrondit et teinte ; ce bloc formate.
 * Sans round (`null`), rien n'est rendu. Les segments partent d'une largeur
 * nulle et s'élargissent en 700 ms (D8).
 */
export function ScoringStats({ stats }: { stats: ScoringStatsData | null }) {
    return stats === null ? null : <StatsBlock stats={stats} />;
}

/**
 * Le bloc rendu avec ses statistiques : son entrée (`useEntered`) part de son
 * montage, à l'arrivée de la prop différée, et non de la page (DO37).
 */
function StatsBlock({ stats }: { stats: ScoringStatsData }) {
    const entered = useEntered();
    const classes = CLASSES.map((entry, index) => ({
        ...entry,
        count: stats.classes[index],
        percent: stats.classPercents[index],
    })).filter((entry) => entry.mark !== 'eagle' || entry.count > 0);
    const values = [
        { label: 'Moy. brut', value: formatTenths(stats.averageGrossT) },
        { label: 'Meilleur brut', value: String(stats.bestGross) },
        { label: 'Par ou mieux', value: `${stats.parOrBetterPercent}%` },
    ];

    return (
        <section aria-labelledby="stats-title" className="mt-[34px]">
            <SectionLabel id="stats-title" value={`${stats.holes} trous`}>
                Stats
            </SectionLabel>
            <dl className="flex border-b border-rule">
                {values.map(({ label, value }) => (
                    <div
                        key={label}
                        className="flex min-w-0 flex-1 flex-col-reverse justify-end gap-2 py-4"
                    >
                        <dt className="font-mono text-caption leading-[1.3] tracking-[0.06em] text-ink-2 uppercase">
                            {label}
                        </dt>
                        <dd className="font-mono text-kpi">{value}</dd>
                    </div>
                ))}
            </dl>
            <div
                aria-hidden="true"
                className="mt-[18px] flex h-3 gap-0.5 overflow-hidden rounded-6 bg-surface-2"
            >
                {classes.map((entry) => (
                    <span
                        key={entry.mark}
                        data-part="segment"
                        className={cn(
                            'h-full transition-[width] duration-700 ease-(--ease-design)',
                            entry.segment,
                        )}
                        style={{
                            width: entered
                                ? share(entry.count, stats.holes)
                                : '0%',
                        }}
                    />
                ))}
            </div>
            <ul aria-label="Répartition des trous" className="mt-2.5">
                {classes.map((entry) => (
                    <li
                        key={entry.mark}
                        data-part="class"
                        className="flex min-h-10 items-center gap-3 border-b border-rule-soft"
                    >
                        <ClassMarkIcon mark={entry.mark} />
                        <span data-part="name" className="text-link">
                            {entry.name}
                        </span>
                        <span
                            data-part="percent"
                            className="ml-auto font-mono text-meta-12 text-ink-2"
                        >
                            {entry.percent}%
                        </span>
                        <span
                            data-part="count"
                            className="w-10 text-right font-mono text-num-m-17"
                        >
                            {entry.count}
                        </span>
                    </li>
                ))}
            </ul>
            {stats.pars.length > 0 ? (
                <ul
                    aria-label="Moyennes par par"
                    className="mt-[22px] flex gap-px overflow-hidden rounded-12 bg-rule"
                >
                    {stats.pars.map((par) => (
                        <li
                            key={par.par}
                            className="flex min-w-0 flex-1 flex-col gap-2 bg-surface-1 px-3 py-3.5"
                        >
                            <span className="font-mono text-caption text-ink-2 uppercase">
                                Par {par.par}
                            </span>
                            <span className="font-mono text-num-l tracking-[-0.03em]">
                                {formatTenths(par.averageT)}
                            </span>
                            <span
                                className={cn(
                                    'font-mono text-section tracking-normal',
                                    TINT_TEXT[par.tint],
                                )}
                            >
                                {signedTenths(par.deltaT)}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    );
}

/**
 * Marque de 18 px d'une classe (design) : double cercle, cercle, trait de
 * par, carré, double carré. Décor : le nom de la classe est écrit à côté.
 */
function ClassMarkIcon({ mark }: { mark: ClassMark }) {
    const shape = {
        eagle: (
            <span className="flex size-4 items-center justify-center rounded-full border-[1.5px] border-signal">
                <span className="size-[9px] rounded-full border-[1.5px] border-signal" />
            </span>
        ),
        birdie: (
            <span className="size-3.5 rounded-full border-[1.5px] border-signal" />
        ),
        par: <span className="h-0.5 w-2.5 bg-ink" />,
        bogey: <span className="size-[13px] border-[1.5px] border-over" />,
        double: (
            <span className="flex size-4 items-center justify-center border-[1.5px] border-over">
                <span className="size-[9px] border-[1.5px] border-over" />
            </span>
        ),
    }[mark];

    return (
        <span
            aria-hidden="true"
            className="flex size-[18px] flex-none items-center justify-center"
        >
            {shape}
        </span>
    );
}
