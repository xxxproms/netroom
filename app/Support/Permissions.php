<?php

namespace App\Support;

/**
 * Every permission NetRoom knows about, and the roles shipped out of the box.
 * Roles are editable afterwards — this is only the starting point, and the
 * source the permission seeder works from.
 */
final class Permissions
{
    /**
     * Permission names must never match a policy ability name (view, create,
     * update, delete). Spatie registers a Gate::before hook that grants any
     * ability whose name the user holds as a permission, which would let a
     * bare "view" permission bypass the site-scoping in every policy.
     */
    public const VIEW = 'view network';

    public const MANAGE_INFRASTRUCTURE = 'manage infrastructure';

    public const MANAGE_VLANS = 'manage vlans';

    public const MANAGE_CABLING = 'manage cabling';

    public const MANAGE_CATALOG = 'manage catalog';

    public const MANAGE_IPAM = 'manage ipam';

    public const IMPORT_EXPORT = 'import export';

    public const VIEW_AUDIT = 'view audit';

    public const MANAGE_USERS = 'manage users';

    public const MANAGE_SETTINGS = 'manage settings';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::MANAGE_INFRASTRUCTURE,
            self::MANAGE_VLANS,
            self::MANAGE_CABLING,
            self::MANAGE_CATALOG,
            self::MANAGE_IPAM,
            self::IMPORT_EXPORT,
            self::VIEW_AUDIT,
            self::MANAGE_USERS,
            self::MANAGE_SETTINGS,
        ];
    }

    /**
     * The default roles: an administrator who runs the panel, an engineer who
     * changes the network, a technician who patches cables, and a viewer.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            'admin' => self::all(),
            'engineer' => [
                self::VIEW,
                self::MANAGE_INFRASTRUCTURE,
                self::MANAGE_VLANS,
                self::MANAGE_CABLING,
                self::MANAGE_CATALOG,
                self::MANAGE_IPAM,
                self::IMPORT_EXPORT,
                self::VIEW_AUDIT,
            ],
            'technician' => [
                self::VIEW,
                self::MANAGE_CABLING,
            ],
            'viewer' => [
                self::VIEW,
            ],
        ];
    }
}
