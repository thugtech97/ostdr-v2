import Dropdown from '@/Components/Dropdown';
import FlashMessages from '@/Components/FlashMessages';
import ThemeToggle from '@/Components/ThemeToggle';
import { usePermissions } from '@/lib/permissions';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Sidebar navigation. Each item names the legacy permission page that must be
 * viewable for it to show, so the menu always matches what the user can open.
 */
const NAVIGATION = [
    {
        label: 'OSTR',
        items: [{ name: 'Dashboard', route: 'dashboard', active: 'dashboard' }],
    },
    {
        label: 'Stock Request',
        items: [
            {
                name: 'Manage Stock Request',
                route: 'stockrequests.index',
                active: ['stockrequests.index', 'stockrequests.show', 'stockrequests.edit'],
                permission: 'Manage Stock Request',
            },
            {
                name: 'Create Stock Request',
                route: 'stockrequests.create',
                active: 'stockrequests.create',
                permission: 'Stock Request',
                action: 'create',
            },
            {
                name: 'Unsaved Stock Request',
                route: 'stockrequests.unsaved',
                active: 'stockrequests.unsaved',
                permission: 'Unsaved Stock Request',
                badge: 'unsaved',
            },
        ],
    },
    {
        label: 'Maintenance',
        items: [
            {
                name: 'Users',
                route: 'users.index',
                active: 'users.*',
                permission: 'Users Maintenance',
            },
            {
                name: 'Roles',
                route: 'roles.index',
                active: 'roles.*',
                permission: 'Roles',
            },
        ],
    },
];

function Brand() {
    return (
        <Link
            href={route('dashboard')}
            className="flex items-center gap-3"
        >
            {/* The logo has navy artwork, so it sits on a white chip in dark mode. */}
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg dark:bg-white dark:p-1">
                <img
                    src="/images/logo.svg"
                    alt="Philsaga Mining Corporation"
                    className="h-full w-full object-contain"
                />
            </span>
            <span className="leading-tight">
                <span className="block text-lg font-bold tracking-tight text-brand-900 dark:text-white">
                    OSTR
                </span>
                <span className="block text-[11px] text-gray-500 dark:text-gray-400">
                    Philsaga Mining Corporation
                </span>
            </span>
        </Link>
    );
}

function Sidebar({ onNavigate }) {
    const can = usePermissions();
    const counts = usePage().props.counts ?? {};

    const sections = NAVIGATION.map((section) => ({
        ...section,
        items: section.items.filter(
            (item) => !item.permission || can(item.permission, item.action),
        ),
    })).filter((section) => section.items.length > 0);

    const isActive = (patterns) =>
        [].concat(patterns).some((pattern) => route().current(pattern));

    return (
        <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            {sections.map((section) => (
                <div key={section.label}>
                    <p className="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {section.label}
                    </p>
                    <ul className="mt-2 space-y-1">
                        {section.items.map((item) => {
                            const active = isActive(item.active);
                            const badge = item.badge ? counts[item.badge] : 0;

                            return (
                                <li key={item.name}>
                                    <Link
                                        href={route(item.route)}
                                        onClick={onNavigate}
                                        aria-current={active ? 'page' : undefined}
                                        className={
                                            'flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm font-medium transition ' +
                                            (active
                                                ? 'bg-brand-50 text-brand-900 dark:bg-gray-800 dark:text-white'
                                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100')
                                        }
                                    >
                                        <span>{item.name}</span>
                                        {badge > 0 && (
                                            <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">
                                                {badge}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-950">
            <FlashMessages />

            {/* Mobile sidebar */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div
                        className="absolute inset-0 bg-gray-900/50"
                        onClick={() => setSidebarOpen(false)}
                    />
                    <aside className="relative flex h-full w-64 flex-col bg-white dark:bg-gray-900">
                        <div className="flex h-16 items-center border-b border-gray-100 px-4 dark:border-gray-800">
                            <Brand />
                        </div>
                        <Sidebar onNavigate={() => setSidebarOpen(false)} />
                    </aside>
                </div>
            )}

            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 hidden w-64 flex-col border-r border-gray-200 bg-white lg:flex dark:border-gray-800 dark:bg-gray-900">
                <div className="flex h-16 items-center border-b border-gray-100 px-4 dark:border-gray-800">
                    <Brand />
                </div>
                <Sidebar />
            </aside>

            <div className="lg:pl-64">
                <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8 dark:border-gray-800 dark:bg-gray-900">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                        aria-label="Open navigation"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div className="lg:hidden">
                        <Brand />
                    </div>

                    <div className="ms-auto flex items-center gap-3">
                        <ThemeToggle className="h-9 w-9" />

                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                                >
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-900 text-xs font-semibold text-white dark:bg-brand-600">
                                        {(user.name || user.username || '?').charAt(0)}
                                    </span>
                                    <span className="hidden text-left leading-tight sm:block">
                                        <span className="block font-medium text-gray-800 dark:text-gray-100">
                                            {user.username}
                                        </span>
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            {user.role}
                                        </span>
                                    </span>
                                </button>
                            </Dropdown.Trigger>

                            <Dropdown.Content>
                                <div className="border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                                    <div className="text-sm font-medium text-gray-800 dark:text-gray-100">
                                        {user.name}
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {user.role} &middot; {user.dept}
                                    </div>
                                </div>
                                <Dropdown.Link href={route('password.edit')}>
                                    Change Password
                                </Dropdown.Link>
                                <Dropdown.Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                >
                                    Log Out
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                {header && (
                    <div className="border-b border-gray-200 bg-white px-4 py-5 sm:px-6 lg:px-8 dark:border-gray-800 dark:bg-gray-900">
                        {header}
                    </div>
                )}

                <main>{children}</main>
            </div>
        </div>
    );
}
