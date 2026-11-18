<div class="flex-1 overflow-auto p-8">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Results Management</h1>
            <p class="text-muted-foreground">Review, approve, and manage test results</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Pending Review</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-file-text h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                        <path d="M10 9H8"></path>
                        <path d="M16 13H8"></path>
                        <path d="M16 17H8"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">13</div>
                    <p class="text-xs text-muted-foreground">Awaiting approval</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Approved Today</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-circle-check-big h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
                        <path d="m9 11 3 3L22 4"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">42</div>
                    <p class="text-xs text-muted-foreground">Results released</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Flagged Results</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-triangle-alert h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">3</div>
                    <p class="text-xs text-muted-foreground">Require attention</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Turnaround Time</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-trending-up h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M16 7h6v6"></path>
                        <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">4.2h</div>
                    <p class="text-xs text-muted-foreground">Average completion</p>
                </div>
            </div>
        </div>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
            <div data-slot="card-header"
                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="leading-none">Recent Results</h4>
                <p data-slot="card-description" class="text-muted-foreground">Latest test results requiring review</p>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                <div class="text-center py-8 text-muted-foreground"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-file-text h-12 w-12 mx-auto mb-4" aria-hidden="true">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                        <path d="M10 9H8"></path>
                        <path d="M16 13H8"></path>
                        <path d="M16 17H8"></path>
                    </svg>
                    <p>Results management interface coming soon</p>
                </div>
            </div>
        </div>
    </div>
</div>