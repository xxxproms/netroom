import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permissions come from the server with every page, so the interface can hide
 * what the current user is not allowed to do. The server still decides —
 * this only keeps the UI honest.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => (page.props.permissions as string[] | undefined) ?? [],
    );

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);

    return { permissions, can };
}

export const Permission = {
    view: 'view network',
    manageInfrastructure: 'manage infrastructure',
    manageVlans: 'manage vlans',
    manageCabling: 'manage cabling',
    manageCatalog: 'manage catalog',
    manageIpam: 'manage ipam',
    importExport: 'import export',
    viewAudit: 'view audit',
    manageUsers: 'manage users',
    manageSettings: 'manage settings',
} as const;
