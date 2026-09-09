// api.ts — cliente GraphQL compartido para las páginas SSG de MascoLive.
// Lecturas (graphql): resilientes, ante fallo devuelven listas vacías + mensaje.
// Mutaciones (graphqlMutation/runMutation): se autentican por HTTP Basic
// (módulo core basic_auth de Drupal). Las mutaciones solo se invocan desde
// el cliente (localStorage/btoa no existen en el build de Node de Astro).

export const WEB = 'http://mascolive.local:8080';
export const ENDPOINT = WEB + '/graphql';

// ⚠️ SOLO demo de laboratorio: guardar credenciales en localStorage no es
// seguro en una app real (un XSS robaría la sesión). Aquí es un simulador.
const AUTH_KEY = 'mascolive_auth';

export function saveAuth(user: string, pass: string): void {
  localStorage.setItem(AUTH_KEY, btoa(`${user}:${pass}`));
}

export function clearAuth(): void {
  localStorage.removeItem(AUTH_KEY);
}

export function getAuth(): { user: string; pass: string } | null {
  try {
    const raw = localStorage.getItem(AUTH_KEY);
    if (!raw) return null;
    const [user, pass] = atob(raw).split(':');
    return user && pass ? { user, pass } : null;
  } catch {
    return null;
  }
}

export function authHeaders(): Record<string, string> {
  const auth = getAuth();
  return auth ? { Authorization: `Basic ${btoa(`${auth.user}:${auth.pass}`)}` } : {};
}

export interface Pet {
  id: string;
  title: string;
  petType?: string;
  clinic?: { id: string; title: string; address?: string; specialty?: string } | null;
}

export interface Clinic {
  id: string;
  title: string;
  address?: string;
  specialty?: string;
  pets?: Pet[];
}

export interface GraphQLResult {
  pets: Pet[];
  clinics: Clinic[];
  error: string | null;
}

export async function graphql(query: string): Promise<GraphQLResult> {
  try {
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    return {
      pets: data?.data?.pets ?? [],
      clinics: data?.data?.clinics ?? [],
      error: null,
    };
  } catch {
    return { pets: [], clinics: [], error: 'No se pudo conectar con el backend' };
  }
}

// Ejecuta una mutation GraphQL con la auth guardada.
// data.data.<mutation> === null => sin permiso o validación fallida.
export async function graphqlMutation<T = unknown>(
  mutation: string,
  variables: Record<string, unknown> = {},
): Promise<{ data: T | null; error: string | null }> {
  try {
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...authHeaders(),
      },
      body: JSON.stringify({ query: mutation, variables }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const payload = await res.json();
    if (payload?.errors?.length) throw new Error(payload.errors[0].message);
    const key = Object.keys(payload?.data ?? {})[0];
    const value = key ? payload.data[key] : null;
    if (value == null) throw new Error('Sin permiso o datos inválidos');
    return { data: value as T, error: null };
  } catch (e) {
    return { data: null, error: e instanceof Error ? e.message : 'No se pudo conectar con el backend' };
  }
}

// Campos a seleccionar por mutation (fine-grained del contrato Drupal).
// '' significa que Drupal devuelve un escalar (Boolean en los delete).
const SELECT: Record<string, string> = {
  createPet: 'id title',
  updatePet: 'id title',
  deletePet: '',
  createClinic: 'id',
  updateClinic: 'id',
  deleteClinic: '',
};

// Arma la query con argumentos inline (JSON.stringify produce literales
// GraphQL válidos para string/number/null) y la ejecuta sin variables.
// Ej: runMutation('updatePet', { id: 7, data: { title: 'Rex' } }) =>
//   mutation { updatePet(id: 7, data: {"title":"Rex"}) { id title } }
export function runMutation(mut: string, args: Record<string, unknown>): Promise<{ data: unknown; error: string | null }> {
  const fields = Object.entries(args)
    .map(([k, v]) => `${k}: ${JSON.stringify(v)}`)
    .join(', ');
  const sel = SELECT[mut] ?? 'id';
  const query = `mutation { ${mut}(${fields})${sel ? ` { ${sel} }` : ''} }`;
  return graphqlMutation(query);
}