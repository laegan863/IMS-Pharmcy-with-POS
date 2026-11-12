import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { navigation } from '@/lib/navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const can = useCan();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { branch } = usePage().props.auth;

    const sections = navigation
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((section) => section.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {branch && (
                    <p
                        data-tour="branch"
                        className="truncate px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden"
                    >
                        {branch.name}
                    </p>
                )}
            </SidebarHeader>

            <SidebarContent>
                {sections.map((section) => (
                    <SidebarGroup
                        key={section.title}
                        data-tour={`nav-${section.title.toLowerCase()}`}
                        className="px-2 py-0"
                    >
                        <SidebarGroupLabel>{section.title}</SidebarGroupLabel>
                        <SidebarMenu>
                            {section.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentOrParentUrl(
                                            item.href,
                                        )}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter data-tour="user-menu">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
