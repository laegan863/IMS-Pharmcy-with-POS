import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Returns a function that checks the signed-in user's permissions, for hiding
 * menus and buttons. The server still authorises every request.
 *
 * @example
 * const can = useCan();
 * if (can('products.manage')) { ... }
 */
export function useCan(): (permission: string | string[]) => boolean {
    const permissions = usePage().props.auth.permissions ?? [];

    return useCallback(
        (permission: string | string[]) => {
            const wanted = Array.isArray(permission) ? permission : [permission];

            return wanted.some((name) => permissions.includes(name));
        },
        [permissions],
    );
}
