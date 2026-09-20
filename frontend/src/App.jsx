import { useEffect, useState } from 'react'
import {
  createDocument,
  listDocuments,
  updateDocumentStatus,
} from './api.js'
import './App.css'

const STATUSES = [
  { value: 'pending', label: 'Pendente' },
  { value: 'processing', label: 'Processando' },
  { value: 'completed', label: 'Concluído' },
  { value: 'failed', label: 'Falhou' },
]

function statusLabel(status) {
  return STATUSES.find((item) => item.value === status)?.label ?? status
}

function formatDate(value) {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleString('pt-BR')
}

export default function App() {
  const [documents, setDocuments] = useState([])
  const [selectedId, setSelectedId] = useState(null)
  const [filename, setFilename] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const selected = documents.find((document) => document.id === selectedId) ?? null

  async function refresh(nextSelectedId = selectedId) {
    const items = await listDocuments()
    setDocuments(items)
    setSelectedId(nextSelectedId)
  }

  useEffect(() => {
    let cancelled = false

    listDocuments()
      .then((items) => {
        if (!cancelled) {
          setDocuments(items)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err.message)
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function handleCreate(event) {
    event.preventDefault()
    const name = filename.trim()

    if (!name) {
      setError('Informe o nome do arquivo.')
      return
    }

    setSaving(true)
    setError(null)

    try {
      const created = await createDocument(name)
      setFilename('')
      await refresh(created.id)
    } catch (err) {
      setError(err.message)
    } finally {
      setSaving(false)
    }
  }

  async function handleStatusChange(status) {
    if (!selected) {
      return
    }

    setSaving(true)
    setError(null)

    try {
      await updateDocumentStatus(selected.id, status)
      await refresh(selected.id)
    } catch (err) {
      setError(err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <main className="page">
      <header className="header">
        <div>
          <p className="eyebrow">Etapa 3</p>
          <h1>Processador de Documentos</h1>
          <p className="lede">
            Interface local da API. Ainda sem upload de arquivo — só o registro
            e o status.
          </p>
        </div>
        <button type="button" className="button secondary" onClick={() => {
          setError(null)
          refresh(selectedId).catch((err) => setError(err.message))
        }}>
          Atualizar
        </button>
      </header>

      {error ? <p className="banner error">{error}</p> : null}

      <section className="card">
        <h2>Novo documento</h2>
        <form className="form" onSubmit={handleCreate}>
          <label htmlFor="filename">Nome do arquivo</label>
          <div className="row">
            <input
              id="filename"
              type="text"
              placeholder="contrato.pdf"
              value={filename}
              onChange={(event) => setFilename(event.target.value)}
              disabled={saving}
            />
            <button type="submit" className="button" disabled={saving}>
              Criar
            </button>
          </div>
        </form>
      </section>

      <div className="layout">
        <section className="card">
          <h2>Documentos</h2>
          {loading ? (
            <p className="muted">Carregando…</p>
          ) : documents.length === 0 ? (
            <p className="muted">Nenhum documento ainda.</p>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nome</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {documents.map((document) => (
                  <tr
                    key={document.id}
                    className={document.id === selectedId ? 'selected' : ''}
                    onClick={() => setSelectedId(document.id)}
                  >
                    <td>{document.id}</td>
                    <td>{document.original_filename}</td>
                    <td>
                      <span className={`status status-${document.status}`}>
                        {statusLabel(document.status)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <section className="card">
          <h2>Detalhe</h2>
          {selected ? (
            <>
              <dl>
                <div>
                  <dt>Nome</dt>
                  <dd>{selected.original_filename}</dd>
                </div>
                <div>
                  <dt>Status</dt>
                  <dd>
                    <span className={`status status-${selected.status}`}>
                      {statusLabel(selected.status)}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt>Criado em</dt>
                  <dd>{formatDate(selected.created_at)}</dd>
                </div>
                <div>
                  <dt>SHA-256</dt>
                  <dd>{selected.sha256 ?? '—'}</dd>
                </div>
              </dl>
              <p className="muted">Alterar status (simula o processamento):</p>
              <div className="actions">
                {STATUSES.map((item) => (
                  <button
                    key={item.value}
                    type="button"
                    className="button secondary"
                    disabled={saving || selected.status === item.value}
                    onClick={() => handleStatusChange(item.value)}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </>
          ) : (
            <p className="muted">Selecione um documento na lista.</p>
          )}
        </section>
      </div>
    </main>
  )
}
