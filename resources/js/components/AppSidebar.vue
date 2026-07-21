<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Cable,
    DoorClosed,
    FileUp,
    FolderGit2,
    LayoutGrid,
    MapPin,
    QrCode,
    Network,
    Router,
    Spline,
    Server,
    Tags,
    EthernetPort,
    Waypoints,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions, Permission } from '@/composables/usePermissions';
import { dashboard, importMethod, labels, map } from '@/routes';
import { index as cables } from '@/routes/cables';
import { index as deviceModels } from '@/routes/device-models';
import { index as devices } from '@/routes/devices';
import { index as racks } from '@/routes/racks';
import { index as rooms } from '@/routes/rooms';
import { index as sites } from '@/routes/sites';
import { index as subnets } from '@/routes/subnets';
import { index as vlanDomains } from '@/routes/vlan-domains';
import { index as vlans } from '@/routes/vlans';
import { index as workplaces } from '@/routes/workplaces';
import type { NavItem } from '@/types';

const { t } = useI18n();
const { can } = usePermissions();

const mainNavItems = computed<NavItem[]>(() => [
    { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
    { title: t('nav.sites'), href: sites(), icon: Building2 },
    { title: t('nav.rooms'), href: rooms(), icon: DoorClosed },
    { title: t('nav.racks'), href: racks(), icon: Server },
    { title: t('nav.devices'), href: devices(), icon: Router },
    { title: t('nav.map'), href: map(), icon: Waypoints },
    { title: t('nav.vlans'), href: vlans(), icon: Network },
    { title: t('nav.cables'), href: cables(), icon: Spline },
    { title: t('nav.workplaces'), href: workplaces(), icon: MapPin },
    { title: t('nav.ipam'), href: subnets(), icon: EthernetPort },
    { title: t('nav.vlanDomains'), href: vlanDomains(), icon: Cable },
    { title: t('nav.models'), href: deviceModels(), icon: Tags },
    { title: t('nav.labels'), href: labels(), icon: QrCode },
    ...(can(Permission.importExport)
        ? [{ title: t('nav.import'), href: importMethod(), icon: FileUp }]
        : []),
]);

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.repository'),
        href: 'https://github.com/xxxproms/netroom',
        icon: FolderGit2,
    },
    {
        title: t('nav.documentation'),
        href: 'https://github.com/xxxproms/netroom#readme',
        icon: BookOpen,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
