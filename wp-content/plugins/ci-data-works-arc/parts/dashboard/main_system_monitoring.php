<div class="flex-1 overflow-auto p-8">
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">System Monitoring</h1>
                <p class="text-muted-foreground">Real-time monitoring of laboratory infrastructure and equipment</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-sm text-muted-foreground">Last updated: 8:01:20 PM</div><button data-slot="button"
                    class="justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md px-3 has-[&gt;svg]:px-2.5 flex items-center gap-2"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-refresh-cw h-4 w-4" aria-hidden="true">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M8 16H3v5"></path>
                    </svg>Refresh</button><button data-slot="dropdown-menu-trigger"
                    class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                    type="button" id="radix-:rh8:" aria-haspopup="menu" aria-expanded="false" data-state="closed"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                        <path d="M12 15V3"></path>
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <path d="m7 10 5 5 5-5"></path>
                    </svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"></path>
                    </svg></button><button data-slot="button"
                    class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-foreground"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-arrow-left h-4 w-4 mr-2" aria-hidden="true">
                        <path d="m12 19-7-7 7-7"></path>
                        <path d="M19 12H5"></path>
                    </svg>Dashboard</button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">System Status</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-monitor h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <rect width="20" height="14" x="2" y="3" rx="2"></rect>
                        <line x1="8" x2="16" y1="21" y2="21"></line>
                        <line x1="12" x2="12" y1="17" y2="21"></line>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold text-green-600">Healthy</div>
                    <p class="text-xs text-muted-foreground mt-1">All systems operational</p>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">System Uptime</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-clock h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M12 6v6l4 2"></path>
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">47 days, 14 hours</div>
                    <p class="text-xs text-muted-foreground mt-1">Since last restart</p>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">Active Users</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-activity h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path
                            d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                        </path>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">24</div>
                    <p class="text-xs text-muted-foreground mt-1">Currently online</p>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header"
                    class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-2">
                    <h4 data-slot="card-title" class="text-sm font-medium">System Load</h4><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-cpu h-4 w-4 text-muted-foreground" aria-hidden="true">
                        <path d="M12 20v2"></path>
                        <path d="M12 2v2"></path>
                        <path d="M17 20v2"></path>
                        <path d="M17 2v2"></path>
                        <path d="M2 12h2"></path>
                        <path d="M2 17h2"></path>
                        <path d="M2 7h2"></path>
                        <path d="M20 12h2"></path>
                        <path d="M20 17h2"></path>
                        <path d="M20 7h2"></path>
                        <path d="M7 20v2"></path>
                        <path d="M7 2v2"></path>
                        <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                        <rect x="8" y="8" width="8" height="8" rx="1"></rect>
                    </svg>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="text-2xl font-bold">45%</div>
                    <div aria-valuemax="100" aria-valuemin="0" role="progressbar" data-state="indeterminate"
                        data-max="100" data-slot="progress"
                        class="bg-primary/20 relative h-2 w-full overflow-hidden rounded-full mt-2">
                        <div data-state="indeterminate" data-max="100" data-slot="progress-indicator"
                            class="bg-primary h-full w-full flex-1 transition-all" style="transform: translateX(-55%);">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div dir="ltr" data-orientation="horizontal" data-slot="tabs" class="flex flex-col gap-2 space-y-4">
            <div role="tablist" aria-orientation="horizontal" data-slot="tabs-list"
                class="bg-muted text-muted-foreground h-9 items-center justify-center rounded-xl p-[3px] grid w-full grid-cols-4"
                tabindex="0" data-orientation="horizontal" style="outline: none;"><button type="button" role="tab"
                    aria-selected="true" aria-controls="radix-:rha:-content-infrastructure" data-state="active"
                    id="radix-:rha:-trigger-infrastructure" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="0" data-orientation="horizontal"
                    data-radix-collection-item="">Infrastructure</button><button type="button" role="tab"
                    aria-selected="false" aria-controls="radix-:rha:-content-equipment" data-state="inactive"
                    id="radix-:rha:-trigger-equipment" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">Equipment</button><button
                    type="button" role="tab" aria-selected="false" aria-controls="radix-:rha:-content-performance"
                    data-state="inactive" id="radix-:rha:-trigger-performance" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="-1" data-orientation="horizontal"
                    data-radix-collection-item="">Performance</button><button type="button" role="tab"
                    aria-selected="false" aria-controls="radix-:rha:-content-events" data-state="inactive"
                    id="radix-:rha:-trigger-events" data-slot="tabs-trigger"
                    class="data-[state=active]:bg-card dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">System Events</button>
            </div>
            <div data-state="active" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:rha:-trigger-infrastructure" id="radix-:rha:-content-infrastructure"
                tabindex="0" data-slot="tabs-content" class="flex-1 outline-none space-y-4" style="">
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-server h-5 w-5" aria-hidden="true">
                                <rect width="20" height="8" x="2" y="2" rx="2" ry="2"></rect>
                                <rect width="20" height="8" x="2" y="14" rx="2" ry="2"></rect>
                                <line x1="6" x2="6.01" y1="6" y2="6"></line>
                                <line x1="6" x2="6.01" y1="18" y2="18"></line>
                            </svg>Infrastructure Components</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-database h-5 w-5 text-gray-600" aria-hidden="true">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg></div>
                                    <div>
                                        <h4 class="font-medium">LIS Application Server</h4>
                                        <p class="text-sm text-muted-foreground">Uptime: 99.98% • Response: 120ms</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm font-medium">CPU: 35%</div>
                                        <div class="text-sm text-muted-foreground">Mem: 68%</div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-green-100 text-green-800">Online</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-database h-5 w-5 text-gray-600" aria-hidden="true">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg></div>
                                    <div>
                                        <h4 class="font-medium">Database Server (Primary)</h4>
                                        <p class="text-sm text-muted-foreground">Uptime: 99.99% • Response: 45ms</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm font-medium">CPU: 28%</div>
                                        <div class="text-sm text-muted-foreground">Mem: 72%</div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-green-100 text-green-800">Online</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-database h-5 w-5 text-gray-600" aria-hidden="true">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg></div>
                                    <div>
                                        <h4 class="font-medium">Database Server (Backup)</h4>
                                        <p class="text-sm text-muted-foreground">Uptime: 99.97% • Response: 48ms</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm font-medium">CPU: 15%</div>
                                        <div class="text-sm text-muted-foreground">Mem: 45%</div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-green-100 text-green-800">Online</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-database h-5 w-5 text-gray-600" aria-hidden="true">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg></div>
                                    <div>
                                        <h4 class="font-medium">Web Application Server</h4>
                                        <p class="text-sm text-muted-foreground">Uptime: 99.95% • Response: 89ms</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm font-medium">CPU: 22%</div>
                                        <div class="text-sm text-muted-foreground">Mem: 56%</div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-green-100 text-green-800">Online</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-database h-5 w-5 text-gray-600" aria-hidden="true">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg></div>
                                    <div>
                                        <h4 class="font-medium">File Storage Server</h4>
                                        <p class="text-sm text-muted-foreground">Uptime: 99.85% • Response: 156ms</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm font-medium">CPU: 65%</div>
                                        <div class="text-sm text-muted-foreground">Mem: 78%</div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-yellow-100 text-yellow-800">Warning</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 border rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-database h-5 w-5 text-gray-600" aria-hidden="true">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg></div>
                                    <div>
                                        <h4 class="font-medium">Backup Server</h4>
                                        <p class="text-sm text-muted-foreground">Uptime: 99.92% • Response: 203ms</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm font-medium">CPU: 12%</div>
                                        <div class="text-sm text-muted-foreground">Mem: 34%</div>
                                    </div><span data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-green-100 text-green-800">Online</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div data-state="inactive" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:rha:-trigger-equipment" id="radix-:rha:-content-equipment" tabindex="0"
                data-slot="tabs-content" class="flex-1 outline-none space-y-4" hidden=""></div>
            <div data-state="inactive" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:rha:-trigger-performance" hidden="" id="radix-:rha:-content-performance"
                tabindex="0" data-slot="tabs-content" class="flex-1 outline-none space-y-4"></div>
            <div data-state="inactive" data-orientation="horizontal" role="tabpanel"
                aria-labelledby="radix-:rha:-trigger-events" hidden="" id="radix-:rha:-content-events" tabindex="0"
                data-slot="tabs-content" class="flex-1 outline-none space-y-4"></div>
        </div>
    </div>
</div>