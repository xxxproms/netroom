import {
    BatteryCharging,
    Building,
    Building2,
    Factory,
    Home,
    MapPin,
    Network,
    PanelTop,
    Router,
    Server,
    ShieldCheck,
    Waypoints,
} from '@lucide/vue';
import type { Component } from 'vue';

/**
 * Shared look-up tables so a device or site draws the same icon and accent
 * colour everywhere on the map. Kinds mirror DeviceModel::KINDS / Site::KINDS.
 */

export const deviceIcon: Record<string, Component> = {
    switch: Network,
    patch_panel: PanelTop,
    router: Router,
    firewall: ShieldCheck,
    server: Server,
    ups: BatteryCharging,
    other: Waypoints,
};

export const deviceColor: Record<string, string> = {
    switch: '#0284c7',
    patch_panel: '#d97706',
    router: '#7c3aed',
    firewall: '#e11d48',
    server: '#059669',
    ups: '#64748b',
    other: '#737373',
};

export const siteIcon: Record<string, Component> = {
    complex: Building2,
    office: Building,
    factory: Factory,
    cottage: Home,
    other: MapPin,
};

/** Dot colour for a device status badge. */
export const statusColor: Record<string, string> = {
    active: '#16a34a',
    spare: '#d97706',
    failed: '#dc2626',
    decommissioned: '#94a3b8',
};

export const kindOf = (map: Record<string, unknown>, kind: string): string =>
    kind in map ? kind : 'other';
