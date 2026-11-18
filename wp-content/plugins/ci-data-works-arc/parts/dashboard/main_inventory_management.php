<div class="flex-1 overflow-auto p-8">
    <div class="space-y-6">
        <div>
            <h1>Inventory Management</h1>
            <p class="text-muted-foreground">Track and manage laboratory supplies, reagents, and equipment</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Total Items</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-package h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path
                            d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                        </path>
                        <path d="M12 22V12"></path>
                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                        <path d="m7.5 4.27 9 5.15"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">6</div>
                    <p class="text-xs text-muted-foreground">Across all categories</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Low Stock Alerts</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-triangle-alert h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold text-destructive">3</div>
                    <p class="text-xs text-muted-foreground">Require attention</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Expiring Soon</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-calendar h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                        <path d="M3 10h18"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold text-yellow-600">3</div>
                    <p class="text-xs text-muted-foreground">Within 30 days</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Total Value</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-trending-down h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M16 17h6v-6"></path>
                        <path d="m22 17-8.5-8.5-5 5L2 7"></path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">$125,420</div>
                    <p class="text-xs text-muted-foreground">Current inventory</p>
                </div>
            </div>
        </div>
        <div dir="ltr" data-orientation="horizontal" data-slot="tabs" class="flex flex-col gap-2 w-full">
            <div role="tablist" aria-orientation="horizontal" data-slot="tabs-list"
                class="bg-muted text-muted-foreground h-9 items-center justify-center rounded-xl p-[3px] grid w-full grid-cols-4"
                tabindex="0" data-orientation="horizontal" style="outline: none;"><button type="button" role="tab"
                    aria-selected="true" aria-controls="radix-:r29:-content-overview" data-state="active"
                    id="radix-:r29:-trigger-overview" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="0" data-orientation="horizontal" data-radix-collection-item="">Overview</button><button
                    type="button" role="tab" aria-selected="false" aria-controls="radix-:r29:-content-items"
                    data-state="inactive" id="radix-:r29:-trigger-items" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">All Items</button><button
                    type="button" role="tab" aria-selected="false" aria-controls="radix-:r29:-content-alerts"
                    data-state="inactive" id="radix-:r29:-trigger-alerts" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">Alerts</button><button
                    type="button" role="tab" aria-selected="false" aria-controls="radix-:r29:-content-reports"
                    data-state="inactive" id="radix-:r29:-trigger-reports" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">Reports</button></div>
            <div data-state="active" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:r29:-trigger-overview" id="radix-:r29:-content-overview" tabindex="0"
                data-slot="tabs-content" class="flex-1 outline-none space-y-4" style="">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="lucide lucide-triangle-alert h-5 w-5 text-destructive" aria-hidden="true">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
                                    </path>
                                    <path d="M12 9v4"></path>
                                    <path d="M12 17h.01"></path>
                                </svg>Critical Stock Levels</h4>
                            <p data-slot="card-description" class="text-muted-foreground">Items requiring immediate
                                attention</p>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-lg border">
                                    <div class="flex items-center gap-3"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-beaker h-4 w-4" aria-hidden="true">
                                            <path d="M4.5 3h15"></path>
                                            <path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"></path>
                                            <path d="M6 14h12"></path>
                                        </svg>
                                        <div>
                                            <p class="font-medium">DNA Extraction Buffer</p>
                                            <p class="text-sm text-muted-foreground">3 bottles remaining</p>
                                        </div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-destructive text-white [a&amp;]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60">Low
                                        Stock</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg border">
                                    <div class="flex items-center gap-3"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-package h-4 w-4" aria-hidden="true">
                                            <path
                                                d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                                            </path>
                                            <path d="M12 22V12"></path>
                                            <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                            <path d="m7.5 4.27 9 5.15"></path>
                                        </svg>
                                        <div>
                                            <p class="font-medium">Pipette Tips (1000μL)</p>
                                            <p class="text-sm text-muted-foreground">50 boxes remaining</p>
                                        </div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-destructive text-white [a&amp;]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60">Low
                                        Stock</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg border">
                                    <div class="flex items-center gap-3"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-shield-check h-4 w-4" aria-hidden="true">
                                            <path
                                                d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                            </path>
                                            <path d="m9 12 2 2 4-4"></path>
                                        </svg>
                                        <div>
                                            <p class="font-medium">Control DNA Samples</p>
                                            <p class="text-sm text-muted-foreground">8 vials remaining</p>
                                        </div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-destructive text-white [a&amp;]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60">Low
                                        Stock</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-calendar h-5 w-5 text-yellow-600"
                                    aria-hidden="true">
                                    <path d="M8 2v4"></path>
                                    <path d="M16 2v4"></path>
                                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                    <path d="M3 10h18"></path>
                                </svg>Expiring Items</h4>
                            <p data-slot="card-description" class="text-muted-foreground">Items expiring within 30 days
                            </p>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-lg border">
                                    <div class="flex items-center gap-3"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-beaker h-4 w-4" aria-hidden="true">
                                            <path d="M4.5 3h15"></path>
                                            <path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"></path>
                                            <path d="M6 14h12"></path>
                                        </svg>
                                        <div>
                                            <p class="font-medium">PCR Master Mix</p>
                                            <p class="text-sm text-muted-foreground">Expires: 2024-03-15</p>
                                        </div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground text-yellow-600 border-yellow-600">-556
                                        days</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg border">
                                    <div class="flex items-center gap-3"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-beaker h-4 w-4" aria-hidden="true">
                                            <path d="M4.5 3h15"></path>
                                            <path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"></path>
                                            <path d="M6 14h12"></path>
                                        </svg>
                                        <div>
                                            <p class="font-medium">DNA Extraction Buffer</p>
                                            <p class="text-sm text-muted-foreground">Expires: 2024-01-20</p>
                                        </div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground text-yellow-600 border-yellow-600">-611
                                        days</span>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg border">
                                    <div class="flex items-center gap-3"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-shield-check h-4 w-4" aria-hidden="true">
                                            <path
                                                d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                            </path>
                                            <path d="m9 12 2 2 4-4"></path>
                                        </svg>
                                        <div>
                                            <p class="font-medium">Control DNA Samples</p>
                                            <p class="text-sm text-muted-foreground">Expires: 2024-02-28</p>
                                        </div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground text-yellow-600 border-yellow-600">-572
                                        days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div data-state="inactive" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:r29:-trigger-items" id="radix-:r29:-content-items" tabindex="0"
                data-slot="tabs-content" class="flex-1 outline-none space-y-4" hidden=""></div>
            <div data-state="inactive" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:r29:-trigger-alerts" hidden="" id="radix-:r29:-content-alerts" tabindex="0"
                data-slot="tabs-content" class="flex-1 outline-none space-y-4"></div>
            <div data-state="inactive" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:r29:-trigger-reports" hidden="" id="radix-:r29:-content-reports" tabindex="0"
                data-slot="tabs-content" class="flex-1 outline-none space-y-4"></div>
        </div>
    </div>
</div>