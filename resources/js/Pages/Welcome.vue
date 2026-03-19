<script setup>
import { ref, onMounted } from "vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});

const isDark = ref(true);

onMounted(() => {
    const saved = localStorage.getItem("edis-theme");
    isDark.value = saved !== "light";
});

function toggleTheme() {
    isDark.value = !isDark.value;
    localStorage.setItem("edis-theme", isDark.value ? "dark" : "light");
}

const services = [
    { title: "Project Management", description: "Organise all your engineering projects with a clear Kanban board, task tracking and progress monitoring." },
    { title: "Task Boards", description: "Drag-and-drop task cards across To Do, In Progress and Done columns for real-time status visibility." },
    { title: "File Attachments", description: "Attach measurement reports, compliance documents and screenshots directly to tasks and projects." },
    { title: "Progress Tracking", description: "Track individual task completion percentage and overall project progress at a glance." },
    { title: "Weekly Calendar", description: "Plan your engineering week with a built-in calendar view that highlights today and weekends." },
    { title: "Team Collaboration", description: "Share projects across your team with role-based access control and activity visibility." },
];

const stats = [
    { value: "40+", label: "Grid compliance studies completed" },
    { value: "2.4 GW", label: "RES and BESS capacity validated" },
    { value: "5", label: "Projects in foreign markets" },
];

const trusted = ["Energetikos projektai", "VICI", "proTECH", "SEL", "met.", "ELINTA", "STIEMO", "ESO", "Ignitis", "Mistral"];
</script>

<template>
    <Head title="EDIS Lab — Project Portal" />

    <!-- Outer wrapper carries .dark class so Tailwind dark: variants activate -->
    <div :class="['font-sans antialiased', { dark: isDark }]">
        <div class="min-h-screen bg-white dark:bg-[#0e0e0e] text-slate-900 dark:text-white">

            <!-- Dot-grid background pattern -->
            <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
                <div
                    class="absolute inset-0"
                    :style="isDark
                        ? 'background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px); background-size: 48px 48px;'
                        : 'background-image: radial-gradient(circle, rgba(0,0,0,0.07) 1px, transparent 1px); background-size: 48px 48px;'"
                ></div>
                <div class="absolute left-1/3 top-1/4 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-slate-700/10 blur-3xl"></div>
                <div class="absolute right-1/4 top-2/3 h-[400px] w-[400px] rounded-full bg-indigo-500/10 blur-3xl"></div>
            </div>

            <!-- Navbar -->
            <header class="relative z-20 border-b border-slate-200 dark:border-white/5">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                    <a href="https://www.edislab.lt" target="_blank" rel="noopener noreferrer">
                        <img :src="isDark ? '/brand/logo-white.svg' : '/brand/logo-color.svg'" alt="EDIS Lab" class="h-8" />
                    </a>

                    <nav class="hidden items-center gap-8 text-sm font-medium text-slate-500 dark:text-white/60 md:flex">
                        <a href="https://www.edislab.lt/#services" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-900 dark:hover:text-white">Services</a>
                        <a href="https://www.edislab.lt/#about" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-900 dark:hover:text-white">About</a>
                        <a href="https://www.edislab.lt/eu-projects" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-900 dark:hover:text-white">EU Projects</a>
                    </nav>

                    <div class="flex items-center gap-3">
                        <!-- Light / Dark toggle -->
                        <button
                            @click="toggleTheme"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 dark:border-white/10 text-slate-500 dark:text-white/50 transition hover:border-slate-300 dark:hover:border-white/30 hover:text-slate-700 dark:hover:text-white/80"
                            :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                        >
                            <!-- Sun: shown in dark mode — click to go light -->
                            <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                            <!-- Moon: shown in light mode — click to go dark -->
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </button>

                        <div v-if="canLogin" class="flex items-center gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
                            >Go to Portal</Link>
                            <template v-else>
                                <Link :href="route('login')" class="text-sm font-medium text-slate-500 dark:text-white/60 transition hover:text-slate-900 dark:hover:text-white">Sign In</Link>
                                <Link v-if="canRegister" :href="route('register')" class="rounded-md border border-slate-200 dark:border-white/20 bg-slate-50 dark:bg-white/5 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-white transition hover:bg-slate-100 dark:hover:bg-white/10">Get Access</Link>
                            </template>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Hero -->
            <section class="relative z-10 px-6 pb-24 pt-28 text-center lg:px-8 lg:pt-36">
                <div class="mx-auto max-w-4xl">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">Internal Project Portal</p>
                    <h1 class="text-4xl font-bold leading-tight tracking-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                        Energy Data, Intelligence<br class="hidden sm:block" />
                        &amp; Solutions
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-500 dark:text-white/55">
                        Manage EDIS Lab engineering projects, compliance studies and field tests — from task creation to final reporting — all in one place.
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-white px-7 py-3 text-sm font-semibold text-white dark:text-[#0e0e0e] transition hover:bg-slate-700 dark:hover:bg-white/90"
                        >
                            Open Portal
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </Link>
                        <template v-else>
                            <Link :href="route('login')" class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-white px-7 py-3 text-sm font-semibold text-white dark:text-[#0e0e0e] transition hover:bg-slate-700 dark:hover:bg-white/90">Sign In</Link>
                            <a href="https://www.edislab.lt" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-md border border-slate-300 dark:border-white/20 px-7 py-3 text-sm font-semibold text-slate-700 dark:text-white transition hover:bg-slate-100 dark:hover:bg-white/10">Visit edislab.lt</a>
                        </template>
                    </div>
                </div>
            </section>

            <!-- Stats -->
            <section class="relative z-10 border-y border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-white/[0.02] px-6 py-14 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <div class="grid gap-px sm:grid-cols-3">
                        <div
                            v-for="(stat, i) in stats"
                            :key="stat.value"
                            class="border-slate-200 dark:border-white/[0.08] py-2 text-center"
                            :class="i > 0 ? 'sm:border-l' : ''"
                        >
                            <p class="text-4xl font-bold text-slate-900 dark:text-white">{{ stat.value }}</p>
                            <p class="mt-2 text-sm text-slate-400 dark:text-white/45">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Trusted by marquee -->
            <section class="relative z-10 overflow-hidden border-b border-slate-200 dark:border-white/5 py-8">
                <p class="mb-5 text-center text-xs font-semibold uppercase tracking-[0.2em] text-slate-300 dark:text-white/25">Trusted by</p>
                <div class="relative flex overflow-hidden">
                    <div class="animate-marquee flex shrink-0 items-center gap-14 whitespace-nowrap pr-14">
                        <span v-for="name in trusted" :key="name" class="text-sm font-medium text-slate-400 dark:text-white/35 transition hover:text-slate-600 dark:hover:text-white/60">{{ name }}</span>
                    </div>
                    <div class="animate-marquee flex shrink-0 items-center gap-14 whitespace-nowrap pr-14" aria-hidden="true">
                        <span v-for="name in trusted" :key="`dup-${name}`" class="text-sm font-medium text-slate-400 dark:text-white/35">{{ name }}</span>
                    </div>
                </div>
            </section>

            <!-- Services / Features -->
            <section class="relative z-10 px-6 py-24 lg:px-8">
                <div class="mx-auto max-w-6xl">
                    <p class="mb-3 text-center text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400">Portal Features</p>
                    <h2 class="mb-14 text-center text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">Everything you need to manage your projects</h2>
                    <div class="grid border border-slate-200 dark:border-white/[0.08] sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="(service, index) in services"
                            :key="service.title"
                            class="group border-slate-200 dark:border-white/[0.08] bg-white dark:bg-[#0e0e0e] p-8 transition duration-200 hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                            :class="{
                                'border-r': index % 3 !== 2,
                                'border-b': index < 3,
                                'sm:border-r': index % 2 === 0,
                                'sm:border-b': index < 4,
                                'lg:border-r': index % 3 !== 2,
                                'lg:border-b': index < 3,
                            }"
                        >
                            <div class="mb-4 h-px w-8 bg-gradient-to-r from-indigo-500 to-transparent transition-all duration-300 group-hover:w-16"></div>
                            <h3 class="mb-2 text-sm font-semibold text-slate-900 dark:text-white">{{ service.title }}</h3>
                            <p class="text-xs leading-relaxed text-slate-400 dark:text-white/45">{{ service.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="relative z-10 border-y border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-white/[0.02] px-6 py-20 lg:px-8">
                <div class="mx-auto flex max-w-4xl flex-col items-center gap-10 lg:flex-row lg:justify-between">
                    <div class="max-w-md text-center lg:text-left">
                        <h2 class="text-2xl font-bold leading-snug text-slate-900 dark:text-white sm:text-3xl">
                            Ready to manage your<br />energy projects?
                        </h2>
                        <p class="mt-3 text-sm text-slate-400 dark:text-white/45">
                            Sign in to the EDIS Lab portal to track tasks, attach compliance documents and collaborate with your team.
                        </p>
                    </div>
                    <div class="flex flex-col items-center gap-3 sm:flex-row">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="rounded-md bg-slate-900 dark:bg-white px-6 py-3 text-sm font-semibold text-white dark:text-[#0e0e0e] transition hover:bg-slate-700 dark:hover:bg-white/90">Open Portal</Link>
                        <template v-else>
                            <Link :href="route('login')" class="rounded-md bg-slate-900 dark:bg-white px-6 py-3 text-sm font-semibold text-white dark:text-[#0e0e0e] transition hover:bg-slate-700 dark:hover:bg-white/90">Sign In</Link>
                            <Link v-if="canRegister" :href="route('register')" class="rounded-md border border-slate-300 dark:border-white/20 px-6 py-3 text-sm font-semibold text-slate-700 dark:text-white transition hover:bg-slate-100 dark:hover:bg-white/10">Request Access</Link>
                        </template>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="relative z-10 px-6 py-14 lg:px-8">
                <div class="mx-auto max-w-6xl">
                    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <img :src="isDark ? '/brand/logo-white.svg' : '/brand/logo-color.svg'" alt="EDIS Lab" class="mb-5 h-7 opacity-70" />
                            <p class="text-xs text-slate-400 dark:text-white/40 leading-relaxed">
                                K. Baršausko g. 59<br />Kaunas, Lithuania<br />
                                <a href="mailto:info@edislab.lt" class="transition hover:text-slate-600 dark:hover:text-white/70">info@edislab.lt</a>
                            </p>
                            <a href="https://www.linkedin.com/company/edis-lab" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex h-8 w-8 items-center justify-center rounded border border-slate-200 dark:border-white/10 text-slate-300 dark:text-white/35 transition hover:border-slate-400 dark:hover:border-white/30 hover:text-slate-600 dark:hover:text-white/70">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M4.98 3.5C4.98 4.88 3.87 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8.5h4V24h-4V8.5zM8.5 8.5h3.8v2.1h.05c.53-1 1.82-2.1 3.75-2.1 4 0 4.74 2.64 4.74 6.07V24h-4v-8.57c0-2.04-.04-4.67-2.85-4.67-2.85 0-3.29 2.22-3.29 4.52V24h-4V8.5z" />
                                </svg>
                            </a>
                        </div>
                        <div>
                            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-300 dark:text-white/25">Company</p>
                            <ul class="space-y-2.5 text-xs text-slate-400 dark:text-white/45">
                                <li><a href="https://www.edislab.lt/#services" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-700 dark:hover:text-white/80">Services</a></li>
                                <li><a href="https://www.edislab.lt/#case-studies" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-700 dark:hover:text-white/80">Case Studies</a></li>
                                <li><a href="https://www.edislab.lt/eu-projects" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-700 dark:hover:text-white/80">EU Projects</a></li>
                                <li><a href="https://www.edislab.lt/#about" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-700 dark:hover:text-white/80">About</a></li>
                            </ul>
                        </div>
                        <div>
                            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-300 dark:text-white/25">Legal</p>
                            <ul class="space-y-2.5 text-xs text-slate-400 dark:text-white/45">
                                <li><a href="https://www.edislab.lt/privacy-policy" target="_blank" rel="noopener noreferrer" class="transition hover:text-slate-700 dark:hover:text-white/80">Privacy Policy</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-12 border-t border-slate-200 dark:border-white/5 pt-6 text-center text-xs text-slate-300 dark:text-white/20">
                        &copy; {{ new Date().getFullYear() }} EDIS LAB. All rights reserved.
                    </div>
                </div>
            </footer>

        </div>
    </div>
</template>

<style scoped>
@keyframes marquee {
    from { transform: translateX(0); }
    to   { transform: translateX(-100%); }
}
.animate-marquee {
    animation: marquee 28s linear infinite;
}
</style>