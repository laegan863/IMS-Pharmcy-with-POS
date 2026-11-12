/**
 * Minimal JSON client for the POS endpoints, which return JSON rather than
 * Inertia pages. Sends Laravel's XSRF token from its cookie.
 */

export class HttpError extends Error {
    constructor(
        public status: number,
        public errors: Record<string, string[]>,
        message: string,
    ) {
        super(message);
    }

    /** First validation message, for showing to the cashier. */
    firstMessage(): string {
        const first = Object.values(this.errors)[0];

        return first?.[0] ?? this.message;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(
    method: string,
    url: string,
    body?: unknown,
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new HttpError(0, {}, 'Lost the connection to the server.');
    }

    if (response.ok) {
        return (await response.json()) as T;
    }

    const payload = (await response.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    throw new HttpError(
        response.status,
        payload.errors ?? {},
        payload.message ?? `Request failed (${response.status}).`,
    );
}

export const http = {
    get: <T>(url: string) => request<T>('GET', url),
    post: <T>(url: string, body: unknown = {}) => request<T>('POST', url, body),
};
