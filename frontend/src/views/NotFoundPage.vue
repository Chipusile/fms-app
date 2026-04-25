<template>
  <div class="flex min-h-screen items-center justify-center px-6">
    <div class="max-w-xl space-y-4 text-center">
      <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
        404
      </p>
      <h1 class="text-4xl font-semibold text-slate-950 dark:text-slate-100">
        Workspace page not found
      </h1>
      <p class="text-sm leading-7 text-slate-600 dark:text-slate-400">
        The requested route does not exist in the current Fleet Management System shell.
      </p>
      <RouterLink
        to="/"
        class="inline-flex items-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
      >
        Return to dashboard
      </RouterLink>
    </div>
  </div>
</template>
