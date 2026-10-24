<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import BuyInController from '@/actions/App/Http/Controllers/BuyInController';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import GameSettingController from '@/actions/App/Http/Controllers/GameSettingController';

import {
    BookOpen,
    Banknote,
    CalendarDays,
    CalendarPlus,
    FolderGit2,
    LayoutGrid,
    UserPlus,
    Users,
    Settings,
} from '@lucide/vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Games',
        href: GameSessionController.index(),
        icon: CalendarDays,
    },
    {
        title: 'Create game',
        href: GameSessionController.create(),
        icon: CalendarPlus,
    },
    {
        title: 'Record buy-in',
        href: BuyInController.create(),
        icon: Banknote,
    },
    {
        title: 'Game settings',
        href: GameSettingController.editStandalone(),
        icon: Settings,
    },
    {
        title: 'Players',
        href: PlayerController.index(),
        icon: Users,
    },
    {
        title: 'Add player',
        href: PlayerController.create(),
        icon: UserPlus,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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
