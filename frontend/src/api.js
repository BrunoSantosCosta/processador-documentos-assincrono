const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000'

async function request(path, options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers ?? {}),
    },
    ...options,
  })

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    const message = data?.message ?? `Erro HTTP ${response.status}`
    throw new Error(message)
  }

  return data
}

export function listDocuments() {
  return request('/api/documents')
}

export function createDocument(originalFilename) {
  return request('/api/documents', {
    method: 'POST',
    body: JSON.stringify({ original_filename: originalFilename }),
  })
}

export function getDocument(id) {
  return request(`/api/documents/${id}`)
}

export function updateDocumentStatus(id, status) {
  return request(`/api/documents/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
}
