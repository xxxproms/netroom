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
    color: string | null;
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
    rear_port_id?: number | null;
    link?: CableLink | null;
};

export type Device = {
    id: number;
    name: string;
    status: string;
    color: string | null;
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

export type Workplace = {
    id: number;
    name: string;
    person: string | null;
    floor: string | null;
    notes?: string | null;
    site_id?: number;
    room_id?: number | null;
    outlets_count?: number;
    room: { id: number; name: string } | null;
    site?: SiteSummary;
    outlets?: Outlet[];
};

export type Outlet = {
    id: number;
    label: string;
    media: string;
    notes: string | null;
    link?: CableLink | null;
};

/** One end of a cable: a device port or a socket at a workplace. */
export type CableEnd =
    | {
          kind: 'port';
          id: number;
          name: string;
          role: string;
          media: string;
          description: string | null;
          device: { id: number; name: string; kind: string; model: string };
      }
    | {
          kind: 'outlet';
          id: number;
          label: string;
          media: string;
          workplace: {
              id: number;
              name: string;
              person: string | null;
              room: string | null;
          };
      };

export type Cable = {
    kind: 'cable';
    id: number;
    media: string;
    strands: number | null;
    label: string | null;
    length_cm: number | null;
    color: string | null;
    status: string;
};

export type CableRow = Cable & {
    site: SiteSummary;
    a: CableEnd;
    b: CableEnd;
};

export type CableLink = {
    cable: Cable;
    far: CableEnd | null;
};

/** A trace alternates ends and the cables between them. */
export type TraceStep = CableEnd | Cable;

export type MapSite = {
    id: number;
    name: string;
    code: string;
    kind: string;
    color: string | null;
    map_x: number | null;
    map_y: number | null;
    rooms_count: number;
    devices_count: number;
};

export type Tunnel = {
    id: number;
    site_a_id: number;
    site_b_id: number;
    type: string;
    status: string;
    label: string | null;
    device_a: { id: number; name: string } | null;
    device_b: { id: number; name: string } | null;
};

export type MapDevice = {
    id: number;
    name: string;
    kind: string;
    model: string;
    color: string | null;
    map_x: number | null;
    map_y: number | null;
};

export type MapLink = {
    id: number;
    a: number;
    b: number;
    media: string;
    strands: number | null;
    label: string | null;
};

export type SubnetSummary = {
    id: number;
    cidr: string;
    name: string | null;
    gateway: string | null;
    vlan: { id: number; vid: number; name: string } | null;
    capacity: number;
    used: number;
    utilisation: number;
    conflicts: number;
};

export type Subnet = {
    id: number;
    cidr: string;
    name: string | null;
    gateway: string | null;
    notes: string | null;
    vlan_domain_id: number;
    vlan_id: number | null;
    domain: { id: number; name: string };
    vlan: { id: number; vid: number; name: string } | null;
};

export type OccupantClaim = {
    source: 'device' | 'reservation';
    id?: number;
    device: { id: number; name: string | null } | null;
    hostname: string | null;
    status: string;
};

export type Occupant = {
    address: string;
    long: number;
    is_gateway: boolean;
    conflict: boolean;
    claims: OccupantClaim[];
};

export type SubnetUsage = {
    capacity: number;
    used: number;
    free: number;
    utilisation: number;
    occupants: Occupant[];
    conflicts: number;
};
