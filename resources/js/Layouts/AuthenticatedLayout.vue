<script setup>
import { computed, onMounted, ref } from 'vue';
import EdisLogo from '@/Components/EdisLogo.vue';
import GlobalNotifications from '@/Components/GlobalNotifications.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { confirmDangerAction, promptForName } from '@/Utils/scrapboardDialogs';

const showingNavigationDropdown = ref(false);
const navigationPosition = ref('top');
const page = usePage();

const scrapboardChildren = computed(() => {
    const boards = Array.isArray(page.props.scrapboardNavigation) ? page.props.scrapboardNavigation : [];

    return [
        ...boards.map((board) => ({
            label: board?.name ?? 'Scrapboard',
            href: board?.id ? route('scrapboards.show', board.id) : route('scrapboards.index'),
            active: board?.id
                ? route().current('scrapboards.show') && String(route().params.scrapboard) === String(board.id)
                : route().current('scrapboards.index'),
            board,
        })),
        {
            label: '+ New board',
            action: 'create-scrapboard',
            active: false,
        },
    ];
});

const createScrapboard = async () => {
    const currentCount = Array.isArray(page.props.scrapboardNavigation) ? page.props.scrapboardNavigation.length : 0;
    const name = await promptForName({
        title: 'Create scrapboard',
        inputLabel: 'Scrapboard name',
        inputValue: `Scrapboard ${currentCount + 1}`,
        placeholder: 'Enter a scrapboard name',
        confirmButtonText: 'Create',
    });

    if (!name) {
        return;
    }

    try {
        const response = await window.axios.post(route('scrapboards.store'), {
            name: name.trim(),
        });

        router.visit(route('scrapboards.show', response.data.id));
    } catch (error) {
        console.error(error);
    }
};

const renameScrapboard = async (board) => {
    if (!board?.id) {
        return;
    }

    const name = await promptForName({
        title: 'Rename scrapboard',
        inputLabel: 'Scrapboard name',
        inputValue: board.name ?? '',
        placeholder: 'Enter a new scrapboard name',
        confirmButtonText: 'Rename',
    });

    if (!name) {
        return;
    }

    try {
        await window.axios.put(route('scrapboards.update', board.id), {
            name: name.trim(),
        });

        router.reload({ only: ['scrapboardNavigation', 'selectedScrapboard', 'scrapboards'] });
    } catch (error) {
        console.error(error);
    }
};

const deleteScrapboard = async (board) => {
    if (!board?.id) {
        return;
    }

    const confirmed = await confirmDangerAction({
        title: 'Delete scrapboard?',
        text: `${board.name} and all sheet data inside it will be removed.`,
        confirmButtonText: 'Delete board',
    });

    if (!confirmed) {
        return;
    }

    try {
        await window.axios.delete(route('scrapboards.destroy', board.id));

        const remainingBoards = Array.isArray(page.props.scrapboardNavigation)
            ? page.props.scrapboardNavigation.filter((item) => String(item.id) !== String(board.id))
            : [];

        if (route().current('scrapboards.*')) {
            const fallbackBoard = remainingBoards[0] ?? null;

            if (fallbackBoard?.id) {
                router.visit(route('scrapboards.show', fallbackBoard.id));
                return;
            }

            router.visit(route('scrapboards.index'));
            return;
        }

        router.reload({ only: ['scrapboardNavigation'] });
    } catch (error) {
        console.error(error);
    }
};

const navigationSections = computed(() => [
    {
        heading: '2026',
        links: [
            {
                label: 'Dashboard',
                href: route('dashboard'),
                active: route().current('dashboard'),
            },
            {
                label: 'Projects',
                href: route('projects.index'),
                active: route().current('projects.index'),
            },
            {
                label: 'People',
                href: route('people.index'),
                active: route().current('people.*'),
                children: [
                    {
                        label: 'Overview',
                        href: route('people.index'),
                        active: route().current('people.index'),
                    },
                    {
                        label: 'Teams',
                        href: route('people.teams.index'),
                        active: route().current('people.teams.index'),
                    },
                    {
                        label: 'Customers',
                        href: route('people.customers.index'),
                        active: route().current('people.customers.index'),
                    },
                    {
                        label: 'Users',
                        href: route('people.users.index'),
                        active: route().current('people.users.index'),
                    },
                ],
            },
        ],
    },
    {
        heading: '2025',
        links: [
            {
                label: 'Dashboard 2025',
                href: route('dashboard.2025'),
                active: route().current('dashboard.2025'),
            },
            {
                label: 'Projects 2025',
                href: route('projects.2025'),
                active: route().current('projects.2025'),
            },
        ],
    },
    {
        heading: 'Scrapboards',
        links: [
            {
                label: 'Scrapboard',
                href: route('scrapboards.index'),
                active: route().current('scrapboards.*'),
                children: scrapboardChildren.value,
            },
        ],
    },
]);

const isLeftNavigation = computed(() => navigationPosition.value === 'left');

const setNavigationPosition = (position) => {
    navigationPosition.value = position;
    localStorage.setItem('app-navigation-position', position);
};

const sidebarLinkClasses = (isActive) => {
    return isActive
        ? 'flex items-center rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700'
        : 'flex items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900';
};

const sidebarChildLinkClasses = (isActive) => {
    return isActive
        ? 'flex items-center rounded-md bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700'
        : 'flex items-center rounded-md px-3 py-2 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800';
};

const topChildLinkClasses = (isActive) => {
    return isActive
        ? 'rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700'
        : 'rounded-md px-2 py-1 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800';
};

const mobileChildLinkClasses = (isActive) => {
    return isActive
        ? 'block rounded-md bg-indigo-50 px-6 py-2 text-sm font-semibold text-indigo-700'
        : 'block rounded-md px-6 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800';
};

onMounted(() => {
    const savedPosition = localStorage.getItem('app-navigation-position');
    if (savedPosition === 'left' || savedPosition === 'top') {
        navigationPosition.value = savedPosition;
    }
});
</script>

<template>
    <div>
        <GlobalNotifications />
        <div class="min-h-screen bg-slate-50" :class="isLeftNavigation ? 'lg:flex' : ''">
            <aside
                v-if="isLeftNavigation"
                class="hidden w-72 shrink-0 border-r border-slate-200 bg-white lg:flex lg:min-h-screen lg:flex-col"
            >
                <div class="border-b border-slate-200 px-6 py-5">
                    <Link :href="route('dashboard')" class="inline-flex items-center">
                        <EdisLogo variant="color" height="34" />
                    </Link>
                </div>

                <div class="flex-1 px-4 py-6">
                    <nav class="space-y-6">
                        <div v-for="section in navigationSections" :key="`sidebar-section-${section.heading}`">
                            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.3em] text-slate-400">{{ section.heading }}</p>
                            <div class="space-y-1">
                                <div v-for="link in section.links" :key="`sidebar-${link.label}`" class="space-y-1">
                                    <Link
                                        :href="link.href"
                                        :class="sidebarLinkClasses(link.active)"
                                    >
                                        {{ link.label }}
                                    </Link>
                                    <div v-if="link.children?.length" class="space-y-1 pl-4">
                                        <div
                                            v-for="child in link.children"
                                            :key="`sidebar-${link.label}-${child.label}`"
                                            class="group flex items-center gap-1"
                                        >
                                            <button
                                                v-if="child.action === 'create-scrapboard'"
                                                type="button"
                                                class="flex w-full items-center rounded-md border border-dashed border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                                @click="createScrapboard"
                                            >
                                                {{ child.label }}
                                            </button>
                                            <template v-else>
                                                <Link
                                                    :href="child.href"
                                                    :class="`${sidebarChildLinkClasses(child.active)} min-w-0 flex-1 justify-between`"
                                                    :title="child.board ? 'Double-click to rename this scrapboard' : child.label"
                                                    @dblclick.prevent.stop="child.board ? renameScrapboard(child.board) : null"
                                                >
                                                    <span class="truncate">{{ child.label }}</span>
                                                </Link>
                                                <button
                                                    v-if="child.board"
                                                    type="button"
                                                    class="rounded-md p-1 text-rose-500 transition hover:bg-rose-50 hover:text-rose-600"
                                                    :class="child.active ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                                                    title="Delete scrapboard"
                                                    @click.prevent="deleteScrapboard(child.board)"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M8.5 2a1 1 0 00-.894.553L7.382 3H5a1 1 0 000 2h.293l.88 9.142A2 2 0 008.163 16h3.674a2 2 0 001.99-1.858L14.707 5H15a1 1 0 100-2h-2.382l-.224-.447A1 1 0 0011.5 2h-3zM8 7a1 1 0 012 0v5a1 1 0 11-2 0V7zm4-1a1 1 0 00-1 1v5a1 1 0 102 0V7a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </nav>
                </div>

                <div class="border-t border-slate-200 p-4">
                    <Dropdown align="left" width="64" direction="up">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50 focus:outline-none"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white uppercase">
                                        {{ $page.props.auth.user.name.charAt(0) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium">{{ $page.props.auth.user.name }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $page.props.auth.user.email }}</span>
                                    </span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <div class="border-b border-slate-200 px-4 py-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Settings</p>
                                <p class="mb-2 text-xs text-slate-500">Navigation position</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1.5 text-xs font-semibold transition"
                                        :class="navigationPosition === 'top' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                                        @click="setNavigationPosition('top')"
                                    >
                                        Top
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1.5 text-xs font-semibold transition"
                                        :class="navigationPosition === 'left' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                                        @click="setNavigationPosition('left')"
                                    >
                                        Left
                                    </button>
                                </div>
                            </div>
                            <DropdownLink :href="route('profile.edit')">
                                Profile
                            </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
            <nav
                class="border-b border-slate-200 bg-white"
                :class="isLeftNavigation ? 'lg:hidden' : ''"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex min-h-16 justify-between py-3">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="inline-flex items-center">
                                    <EdisLogo variant="color" height="34" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden gap-6 sm:ms-10 sm:flex sm:items-start"
                                v-if="!isLeftNavigation"
                            >
                                <div v-for="section in navigationSections" :key="`top-section-${section.heading}`" class="flex items-start gap-3">
                                    <span class="mr-1 text-[11px] font-semibold uppercase tracking-[0.3em] text-slate-400">{{ section.heading }}</span>
                                    <div class="flex items-start gap-4">
                                        <div v-for="link in section.links" :key="`top-${link.label}`" class="space-y-1">
                                            <NavLink
                                                :href="link.href"
                                                :active="link.active"
                                            >
                                                {{ link.label }}
                                            </NavLink>
                                            <div v-if="link.children?.length" class="flex flex-wrap gap-1 pl-1">
                                                <template v-for="child in link.children" :key="`top-${link.label}-${child.label}`">
                                                    <button
                                                        v-if="child.action === 'create-scrapboard'"
                                                        type="button"
                                                        class="rounded-md border border-dashed border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                                        @click="createScrapboard"
                                                    >
                                                        {{ child.label }}
                                                    </button>
                                                    <Link
                                                        v-else
                                                        :href="child.href"
                                                        :class="topChildLinkClasses(child.active)"
                                                        :title="child.board ? 'Double-click to rename this scrapboard' : child.label"
                                                        @dblclick.prevent.stop="child.board ? renameScrapboard(child.board) : null"
                                                    >
                                                        {{ child.label }}
                                                    </Link>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium leading-4 text-slate-700 transition duration-150 ease-in-out hover:bg-slate-50 focus:outline-none"
                                            >
                                                <!-- User avatar circle -->
                                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white uppercase">
                                                    {{ $page.props.auth.user.name.charAt(0) }}
                                                </span>
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="h-4 w-4 text-slate-500"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="border-b border-slate-200 px-4 py-3">
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Settings</p>
                                            <p class="mb-2 text-xs text-slate-500">Navigation position</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-md border px-2 py-1.5 text-xs font-semibold transition"
                                                    :class="navigationPosition === 'top' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                                                    @click="setNavigationPosition('top')"
                                                >
                                                    Top
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md border px-2 py-1.5 text-xs font-semibold transition"
                                                    :class="navigationPosition === 'left' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                                                    @click="setNavigationPosition('left')"
                                                >
                                                    Left
                                                </button>
                                            </div>
                                        </div>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-slate-500 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-700 focus:bg-slate-100 focus:text-slate-700 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-4 pb-3 pt-2">
                        <div v-for="section in navigationSections" :key="`mobile-section-${section.heading}`">
                            <p class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-[0.3em] text-slate-400">{{ section.heading }}</p>
                            <div v-for="link in section.links" :key="`mobile-${link.label}`" class="space-y-1">
                                <ResponsiveNavLink
                                    :href="link.href"
                                    :active="link.active"
                                >
                                    {{ link.label }}
                                </ResponsiveNavLink>
                                <div v-if="link.children?.length" class="space-y-1">
                                    <template v-for="child in link.children" :key="`mobile-${link.label}-${child.label}`">
                                        <button
                                            v-if="child.action === 'create-scrapboard'"
                                            type="button"
                                            class="block w-full rounded-md border border-dashed border-slate-300 px-6 py-2 text-left text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                                            @click="createScrapboard"
                                        >
                                            {{ child.label }}
                                        </button>
                                        <Link
                                            v-else
                                            :href="child.href"
                                            :class="mobileChildLinkClasses(child.active)"
                                            :title="child.board ? 'Double-click to rename this scrapboard' : child.label"
                                            @dblclick.prevent.stop="child.board ? renameScrapboard(child.board) : null"
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-slate-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-slate-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-slate-300">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <div class="px-4 pb-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Settings</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1.5 text-xs font-semibold transition"
                                        :class="navigationPosition === 'top' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                                        @click="setNavigationPosition('top')"
                                    >
                                        Top
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1.5 text-xs font-semibold transition"
                                        :class="navigationPosition === 'left' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                                        @click="setNavigationPosition('left')"
                                    >
                                        Left
                                    </button>
                                </div>
                            </div>
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="border-b border-slate-200 bg-white shadow-sm"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
            </div>
        </div>
    </div>
</template>
