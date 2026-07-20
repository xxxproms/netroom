export type SiteSummary = {
    id: number;
    name: string;
    code: string;
};

export type SiteContext = {
    current: SiteSummary | null;
    available: SiteSummary[];
};

export type VlanDomainSummary = {
    id: number;
    name: string;
};

export type Site = SiteSummary & {
    kind: string;
    city: string | null;
    address?: string | null;
    color: string | null;
    notes?: string | null;
    vlan_domain_id?: number;
    rooms_count?: number;
    vlan_domain?: VlanDomainSummary & { sites_count?: number };
    rooms?: Room[];
};

export type Room = {
    id: number;
    name: string;
    kind: string;
    floor: string | null;
    notes?: string | null;
    site_id?: number;
    racks_count?: number;
    site?: SiteSummary;
    racks?: Rack[];
};

export type Rack = {
    id: number;
    name: string;
    kind: string;
    u_height: number;
    notes?: string | null;
    room?: { id: number; name: string };
    site?: { id: number; code: string };
};

export type PortTemplate = {
    id?: number;
    name_prefix: string;
    start_number: number;
    count: number;
    media: string;
    speed_mbps: number | null;
    role: string;
};

export type DeviceModel = {
    id: number;
    vendor: string;
    model: string;
    kind: string;
    u_height: number;
    notes: string | null;
    port_count: number;
    port_templates: PortTemplate[];
};

export type Vlan = {
    id: number;
    vid: number;
    name: string;
    description: string | null;
    color: string | null;
};

export type VlanDomain = VlanDomainSummary & {
    notes: string | null;
    sites_count: number;
    vlans_count: number;
    sites: SiteSummary[];
};

export type DeviceModelSummary = {
    id?: number;
    vendor: string;
    model: string;
    kind: string;
    u_height: number;
};

export type RackDevice = {
    id: number;
    name: string;
    face: string;
    position_u: number;
    status: string;
    mgmt_ip: string | null;
    model: DeviceModelSummary;
};

export type Port = {
    id: number;
    name: string;
    number: number;
    media: string;
    speed_mbps: number | null;
    role: string;
    is_uplink: boolean;
    enabled: boolean;
    description: string | null;
};

export type Device = {
    id: number;
    name: string;
    status: string;
    position_u: number | null;
    mgmt_ip: string | null;
    mgmt_url?: string | null;
    serial?: string | null;
    notes?: string | null;
    face?: string;
    ports_count: number;
    device_model_id?: number;
    site_id?: number;
    rack_id?: number | null;
    model: DeviceModelSummary;
    site: SiteSummary;
    rack: { id: number; name: string } | null;
    room?: { id: number; name: string } | null;
    ports?: Port[];
};

export type RackDetail = Rack & {
    room: { id: number; name: string };
    site: SiteSummary;
};
