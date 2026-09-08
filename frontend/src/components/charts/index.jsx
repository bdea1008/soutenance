import { useLayoutEffect, useRef, useState } from 'react'
import { formatCompactNumber } from '../../utils/format'
import { INK, PRIMARY, SERIES, TRACK } from './palette'

/* =========================================================================
   Primitives de visualisation (§7.6)
   SVG écrit à la main, sans librairie : le cahier des charges vise des
   connexions limitées, et une dépendance de graphiques pèse plus lourd que
   l'application entière.

   Règles tenues partout ici : marques fines, grille en filet plein (jamais
   pointillée), écart de 2 px en couleur de surface entre deux aplats voisins,
   anneau de surface sur les points, libellés parcimonieux, texte en encre
   (jamais en couleur de série), et une vue tableau pour chaque graphique.
   ========================================================================= */

/** Largeur réelle du conteneur — évite de déformer le texte via un viewBox élastique. */
function useWidth() {
  const ref = useRef(null)
  const [width, setWidth] = useState(0)

  useLayoutEffect(() => {
    const node = ref.current
    if (!node) return undefined

    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width))
    observer.observe(node)
    setWidth(node.getBoundingClientRect().width)

    return () => observer.disconnect()
  }, [])

  return [ref, width]
}

/** Bornes d'axe arrondies à un pas lisible (0 / 20 000 / 40 000…). */
function niceTicks(max, count = 4) {
  if (max <= 0) return [0]

  const raw = max / count
  const magnitude = 10 ** Math.floor(Math.log10(raw))
  const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= raw) ?? magnitude * 10

  const ticks = []
  for (let v = 0; v <= max + step / 2; v += step) ticks.push(v)
  return ticks
}

/* --- Enveloppe commune ---------------------------------------------------- */

/**
 * Carte de graphique : titre, sous-titre, et bascule vers la vue tableau.
 * Le tableau n'est pas un supplément : c'est l'équivalent accessible du
 * graphique, et la compensation exigée par les couleurs à faible contraste.
 */
export function ChartCard({ title, subtitle, children, table, footer }) {
  const [showTable, setShowTable] = useState(false)

  return (
    <section className="card chart-card">
      <header className="chart-card__head">
        <div>
          <h3 className="chart-card__title">{title}</h3>
          {subtitle && <p className="chart-card__subtitle">{subtitle}</p>}
        </div>
        {table && (
          <button
            className="chart-card__toggle"
            onClick={() => setShowTable((v) => !v)}
            aria-expanded={showTable}
          >
            {showTable ? 'Voir le graphique' : 'Voir les données'}
          </button>
        )}
      </header>

      {showTable && table ? <div className="chart-table__wrap">{table}</div> : children}

      {footer && <p className="chart-card__footer">{footer}</p>}
    </section>
  )
}

/** Infobulle positionnée au-dessus du graphique. */
function Tooltip({ x, y, children }) {
  return (
    <div className="chart-tip" style={{ left: x, top: y }} role="status">
      {children}
    </div>
  )
}

/* --- Chiffres ------------------------------------------------------------- */

/**
 * Tuile de statistique. Un nombre seul n'a pas besoin d'un graphique à une
 * barre : la valeur *est* le graphique.
 */
export function StatTile({ label, value, hint, tone }) {
  return (
    <div className={`stat-tile${tone ? ` stat-tile--${tone}` : ''}`}>
      <div className="stat-tile__label">{label}</div>
      <div className="stat-tile__value">{value}</div>
      {hint && <div className="stat-tile__hint">{hint}</div>}
    </div>
  )
}

/**
 * Jauge : un ratio face à une limite. La piste est un pas clair de la même
 * rampe que le remplissage, pour que l'état se lise sur toute la barre.
 */
export function Meter({ label, value, caption, color = PRIMARY }) {
  const pct = Math.max(0, Math.min(100, Number(value) || 0))

  return (
    <div className="meter">
      <div className="meter__head">
        <span className="meter__label">{label}</span>
        <b className="meter__value">{pct.toFixed(0)} %</b>
      </div>
      <div className="meter__track" style={{ background: TRACK }}>
        <span style={{ width: `${pct}%`, background: color }} />
      </div>
      {caption && <div className="meter__caption">{caption}</div>}
    </div>
  )
}

/* --- Barres horizontales -------------------------------------------------- */

/**
 * Comparaison de magnitudes entre catégories nominales.
 * Une seule couleur pour toutes les barres : teinter « plus foncé quand plus
 * grand » redirait la longueur et gaspillerait le seul canal libre.
 */
export function BarChart({ data, format = (v) => v, height = 26, color = PRIMARY }) {
  const [ref, width] = useWidth()
  const [hover, setHover] = useState(null)

  const rows = data.filter((d) => d.value !== null && d.value !== undefined)
  const max = Math.max(...rows.map((d) => d.value), 1)

  const labelWidth = Math.min(170, Math.max(90, width * 0.28))
  // Troncature calculée sur la gouttière réelle : une limite en dur déborde
  // sur les barres dès que la carte passe en demi-largeur.
  const maxLabelChars = Math.max(8, Math.floor((labelWidth - 10) / 7.2))
  const valueWidth = 104
  const trackWidth = Math.max(40, width - labelWidth - valueWidth)
  const barHeight = Math.min(24, height - 8) // jamais plus de 24 px d'épaisseur

  return (
    <div className="chart" ref={ref}>
      {width > 0 && (
        <svg width={width} height={rows.length * height + 4} role="img">
          {rows.map((row, i) => {
            const y = i * height
            const w = Math.max(2, (row.value / max) * trackWidth)

            return (
              <g
                key={row.label}
                onMouseEnter={() => setHover({ ...row, y: y + height })}
                onMouseLeave={() => setHover(null)}
              >
                {/* Cible de survol généreuse : toute la ligne, pas la barre seule. */}
                <rect x="0" y={y} width={width} height={height} fill="transparent" />

                <text
                  x="0" y={y + height / 2} dominantBaseline="central"
                  className="chart__label" fill={INK.secondary}
                >
                  {row.label.length > maxLabelChars
                    ? `${row.label.slice(0, maxLabelChars - 1)}…`
                    : row.label}
                </text>

                {/* Extrémité arrondie côté valeur, droite contre la ligne de base. */}
                <rect
                  x={labelWidth} y={y + (height - barHeight) / 2}
                  width={w} height={barHeight}
                  rx="4" ry="4"
                  fill={row.color || color}
                />
                <rect
                  x={labelWidth} y={y + (height - barHeight) / 2}
                  width={Math.min(4, w)} height={barHeight}
                  fill={row.color || color}
                />

                <text
                  x={labelWidth + w + 8} y={y + height / 2} dominantBaseline="central"
                  className="chart__value" fill={INK.primary}
                >
                  {format(row.value)}
                </text>
              </g>
            )
          })}
        </svg>
      )}

      {hover && (
        <Tooltip x={16} y={hover.y}>
          <b>{hover.label}</b>
          <span>{format(hover.value)}</span>
          {hover.note && <span className="chart-tip__note">{hover.note}</span>}
        </Tooltip>
      )}
    </div>
  )
}

/* --- Barre empilée (part-à-tout) ------------------------------------------ */

/**
 * Répartition d'un tout entre quelques catégories. Horizontale : les libellés
 * métier (« résidentiel », « commercial ») sont longs.
 * Les segments sont séparés par un vide de 2 px en couleur de surface — jamais
 * par un contour, qui ajouterait de l'encre non porteuse de donnée.
 */
export function StackedBar({ data, format = (v) => v, height = 34 }) {
  const [ref, width] = useWidth()
  const [hover, setHover] = useState(null)

  const total = data.reduce((sum, d) => sum + d.value, 0) || 1
  const GAP = 2

  let offset = 0
  const segments = data.map((d, i) => {
    const raw = (d.value / total) * (width - GAP * (data.length - 1))
    const seg = { ...d, x: offset, width: Math.max(0, raw), color: SERIES[i % SERIES.length] }
    offset += raw + GAP
    return seg
  })

  return (
    <div className="chart" ref={ref}>
      {width > 0 && (
        <>
          <svg width={width} height={height} role="img">
            {segments.map((s) => (
              <rect
                key={s.label}
                x={s.x} y="0" width={s.width} height={height}
                rx="4" ry="4"
                fill={s.color}
                onMouseEnter={() => setHover(s)}
                onMouseLeave={() => setHover(null)}
              />
            ))}
          </svg>

          {/* Légende : le canal d'identité fiable, présent dès deux séries. */}
          <ul className="chart-legend">
            {segments.map((s) => (
              <li key={s.label}>
                <span className="chart-legend__swatch" style={{ background: s.color }} />
                <span className="chart-legend__label">{s.label}</span>
                <b className="chart-legend__value">{format(s.value)}</b>
                <span className="chart-legend__share">
                  {Math.round((s.value / total) * 100)} %
                </span>
              </li>
            ))}
          </ul>
        </>
      )}

      {hover && (
        <Tooltip x={Math.min(hover.x, width - 160)} y={height + 6}>
          <b>{hover.label}</b>
          <span>{format(hover.value)} · {Math.round((hover.value / total) * 100)} %</span>
        </Tooltip>
      )}
    </div>
  )
}

/* --- Aire temporelle ------------------------------------------------------ */

/**
 * Évolution d'une grandeur unique dans le temps. Une seule série : pas de
 * légende (le titre dit ce qui est tracé), un lavis à 10 % sous la courbe,
 * et une valeur directement posée sur le dernier point.
 */
export function AreaChart({
  data,
  format = (v) => v,
  // Les graduations portent un format court, sans devise : « 150 M FCFA »
  // déborderait du cadre à gauche. L'unité est dans le titre de la carte.
  tickFormat = formatCompactNumber,
  height = 220,
  color = PRIMARY,
}) {
  const [ref, width] = useWidth()
  const [hover, setHover] = useState(null)

  const max = Math.max(...data.map((d) => d.value), 1)
  const ticks = niceTicks(max)
  const scaleMax = ticks[ticks.length - 1] || 1

  // Gouttière gauche dimensionnée sur la graduation la plus large, jamais fixe.
  const widestTick = Math.max(...ticks.map((t) => tickFormat(t).length))
  const PAD = { top: 16, right: 16, bottom: 30, left: Math.max(34, widestTick * 7 + 14) }
  const plotW = Math.max(10, width - PAD.left - PAD.right)
  const plotH = height - PAD.top - PAD.bottom

  const x = (i) => PAD.left + (data.length <= 1 ? plotW / 2 : (i / (data.length - 1)) * plotW)
  const y = (v) => PAD.top + plotH - (v / scaleMax) * plotH

  const line = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(d.value)}`).join(' ')
  const area = `${line} L ${x(data.length - 1)} ${PAD.top + plotH} L ${x(0)} ${PAD.top + plotH} Z`

  // Un libellé sur deux au maximum : douze dates côte à côte se chevauchent.
  const labelEvery = data.length > 8 ? 3 : data.length > 5 ? 2 : 1

  function onMove(e) {
    const box = e.currentTarget.getBoundingClientRect()
    const rel = e.clientX - box.left - PAD.left
    const i = Math.max(0, Math.min(data.length - 1, Math.round((rel / plotW) * (data.length - 1))))
    setHover({ i, ...data[i] })
  }

  const last = data[data.length - 1]

  return (
    <div className="chart" ref={ref}>
      {width > 0 && (
        <svg
          width={width} height={height} role="img"
          onMouseMove={onMove}
          onMouseLeave={() => setHover(null)}
        >
          {/* Grille : filets pleins, une nuance au-dessus de la surface. */}
          {ticks.map((t) => (
            <g key={t}>
              <line
                x1={PAD.left} x2={PAD.left + plotW} y1={y(t)} y2={y(t)}
                stroke={INK.grid} strokeWidth="1"
              />
              <text
                x={PAD.left - 8} y={y(t)} textAnchor="end" dominantBaseline="central"
                className="chart__tick" fill={INK.muted}
              >
                {tickFormat(t)}
              </text>
            </g>
          ))}

          <path d={area} fill={color} opacity="0.1" />
          <path d={line} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />

          {data.map((d, i) => (
            i % labelEvery === 0 && (
              <text
                key={d.label} x={x(i)} y={height - 10} textAnchor="middle"
                className="chart__tick" fill={INK.muted}
              >
                {d.label}
              </text>
            )
          ))}

          {/* Point final : anneau de surface pour rester lisible sur la courbe. */}
          <circle cx={x(data.length - 1)} cy={y(last.value)} r="6" fill={INK.surface} />
          <circle cx={x(data.length - 1)} cy={y(last.value)} r="4.5" fill={color} />

          {hover && (
            <g>
              <line
                x1={x(hover.i)} x2={x(hover.i)} y1={PAD.top} y2={PAD.top + plotH}
                stroke={INK.axis} strokeWidth="1"
              />
              <circle cx={x(hover.i)} cy={y(hover.value)} r="6" fill={INK.surface} />
              <circle cx={x(hover.i)} cy={y(hover.value)} r="4.5" fill={color} />
            </g>
          )}
        </svg>
      )}

      {hover && (
        <Tooltip
          x={Math.min(Math.max(0, x(hover.i) - 70), Math.max(0, width - 150))}
          y={8}
        >
          <b>{hover.label}</b>
          <span>{format(hover.value)}</span>
        </Tooltip>
      )}
    </div>
  )
}

/* --- Vue tableau ---------------------------------------------------------- */

/** Équivalent accessible d'un graphique : toute valeur y est lisible en texte. */
export function DataTable({ columns, rows }) {
  return (
    <table className="chart-table">
      <thead>
        <tr>{columns.map((c) => <th key={c} scope="col">{c}</th>)}</tr>
      </thead>
      <tbody>
        {rows.map((row, i) => (
          <tr key={i}>
            {row.map((cell, j) => (
              j === 0
                ? <th key={j} scope="row">{cell}</th>
                : <td key={j}>{cell}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  )
}

/** Pastille d'état : couleur *et* libellé, jamais la couleur seule. */
export function StatusDot({ color, label }) {
  return (
    <span className="status-dot">
      <span className="status-dot__mark" style={{ background: color }} />
      {label}
    </span>
  )
}
