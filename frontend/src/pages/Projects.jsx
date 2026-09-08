import { useEffect, useState } from 'react'
import client from '../api/client'
import ProjectCard from '../components/ProjectCard'

export default function Projects() {
  const [projects, setProjects] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')

  function load(params = {}) {
    setLoading(true)
    client
      .get('/projects', { params: { per_page: 24, ...params } })
      .then((res) => setProjects(res.data.data))
      .catch(() => setProjects([]))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  function handleSearch(e) {
    e.preventDefault()
    load(search ? { search } : {})
  }

  return (
    <section className="section">
      <div className="container">
        <div className="section__head">
          <p className="eyebrow">Catalogue</p>
          <h2>Projets immobiliers</h2>
          <p className="text-muted">
            Parcourez les projets ouverts au co-investissement. Connectez-vous pour voir le détail et investir.
          </p>
        </div>

        <form onSubmit={handleSearch} style={{ display: 'flex', gap: '0.6rem', maxWidth: 460, marginBottom: '2rem' }}>
          <input
            className="field"
            style={{ margin: 0, flex: 1, padding: '0.65rem 0.8rem', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-sm)' }}
            placeholder="Rechercher (ville, titre…)"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <button className="btn btn--primary">Rechercher</button>
        </form>

        {loading ? (
          <div className="spinner" />
        ) : projects.length === 0 ? (
          <p className="text-muted">Aucun projet trouvé.</p>
        ) : (
          <div className="grid grid--3">
            {projects.map((p) => <ProjectCard key={p.id} project={p} />)}
          </div>
        )}
      </div>
    </section>
  )
}
