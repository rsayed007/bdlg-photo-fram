/** Laravel sets XSRF-TOKEN as a cookie; send it back as X-XSRF-TOKEN. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export class HttpError extends Error {
    constructor(
        public status: number,
        public body: { message?: string; errors?: Record<string, string[]> } | null,
    ) {
        super(body?.errors ? Object.values(body.errors)[0]?.[0] : (body?.message ?? `HTTP ${status}`));
    }
}

/** POST multipart with upload progress (fetch has no upload progress). */
export function postForm<T>(url: string, data: FormData, onProgress?: (pct: number) => void): Promise<T> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress?.(Math.round((e.loaded / e.total) * 100));
        xhr.onload = () => {
            let body = null;
            try {
                body = JSON.parse(xhr.responseText);
            } catch {
                /* non-JSON error page */
            }
            if (xhr.status >= 200 && xhr.status < 300) resolve(body as T);
            else reject(new HttpError(xhr.status, body));
        };
        xhr.onerror = () => reject(new HttpError(0, null));
        xhr.send(data);
    });
}

export async function postJson<T>(url: string, data: Record<string, unknown> = {}): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(data),
    });
    const body = await res.json().catch(() => null);
    if (!res.ok) throw new HttpError(res.status, body);
    return body as T;
}

export async function getJson<T>(url: string): Promise<T> {
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const body = await res.json().catch(() => null);
    if (!res.ok) throw new HttpError(res.status, body);
    return body as T;
}
