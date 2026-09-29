import { usePage } from '@inertiajs/react';

/**
 * Returns can(page, action) for the signed-in user, mirroring the server's
 * AccessRightService: ADMIN can do everything, others need the action listed.
 */
export function usePermissions() {
    const { isAdmin, permissions } = usePage().props.auth;

    return (page, action = 'view') =>
        isAdmin || (permissions?.[page] ?? []).includes(action);
}
