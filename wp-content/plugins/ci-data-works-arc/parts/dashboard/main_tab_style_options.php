<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-semibold">Tab Style Options</h1>
        <p class="text-muted-foreground mt-1 text-[13px]">Compare different tab navigation styles for content-heavy
            pages like Laboratory Settings</p>
    </div>
    <div data-slot="card"
        class="text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-green-200 bg-green-50">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
            <h4 data-slot="card-title" class="leading-none text-green-800">✅ Tab Demo Working!</h4>
            <p data-slot="card-description" class="text-green-700">Great! The navigation is working correctly. You can
                now test the different tab styles below.</p>
        </div>
    </div>
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
            <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-eye h-5 w-5 text-primary" aria-hidden="true">
                    <path
                        d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0">
                    </path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>Choose a Tab Style</h4>
            <p data-slot="card-description" class="text-muted-foreground">Select different tab styles to preview how
                they would look with your Laboratory Settings content</p>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl cursor-pointer transition-all duration-200 hover:scale-[1.02] border-2 border-gray-200 hover:border-primary/30">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-2 rounded-lg bg-gray-100 text-gray-700 border-gray-300"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-panels-top-left h-4 w-4"
                                    aria-hidden="true">
                                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                    <path d="M3 9h18"></path>
                                    <path d="M9 21V9"></path>
                                </svg></div>
                            <div>
                                <h3 class="font-medium text-sm">Current Style</h3>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mb-3 line-clamp-2">Your existing Laboratory Settings
                            implementation</p>
                        <div class="space-y-1">
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Grid-based tabs
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Primary/secondary structure
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Familiar layout
                            </div>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="text-card-foreground flex flex-col gap-6 rounded-xl cursor-pointer transition-all duration-200 hover:scale-[1.02] border-2 border-primary bg-primary/5 shadow-lg">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-2 rounded-lg bg-primary text-primary-foreground"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-palette h-4 w-4" aria-hidden="true">
                                    <path
                                        d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z">
                                    </path>
                                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>
                                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>
                                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>
                                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>
                                </svg></div>
                            <div>
                                <h3 class="font-medium text-sm">Pills Style</h3><span data-slot="badge"
                                    class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-primary text-primary-foreground [a&amp;]:hover:bg-primary/90 mt-1 text-xs">Selected</span>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mb-3 line-clamp-2">Modern rounded pill-shaped tabs with
                            hover animations</p>
                        <div class="space-y-1">
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Rounded pills
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Gradient backgrounds
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Smooth animations
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Compact layout
                            </div>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl cursor-pointer transition-all duration-200 hover:scale-[1.02] border-2 border-gray-200 hover:border-primary/30">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-2 rounded-lg bg-green-100 text-green-700 border-green-300"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-navigation h-4 w-4" aria-hidden="true">
                                    <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                                </svg></div>
                            <div>
                                <h3 class="font-medium text-sm">Cards Style</h3>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mb-3 line-clamp-2">Card-based navigation with icons and
                            descriptions</p>
                        <div class="space-y-1">
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Visual cards
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Icons &amp; badges
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Hover effects
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Grid layout
                            </div>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl cursor-pointer transition-all duration-200 hover:scale-[1.02] border-2 border-gray-200 hover:border-primary/30">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-2 rounded-lg bg-purple-100 text-purple-700 border-purple-300"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-panel-left h-4 w-4" aria-hidden="true">
                                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                    <path d="M9 3v18"></path>
                                </svg></div>
                            <div>
                                <h3 class="font-medium text-sm">Sidebar Style</h3>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mb-3 line-clamp-2">Vertical sidebar navigation with
                            expandable sections</p>
                        <div class="space-y-1">
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Vertical layout
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Collapsible sections
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Space efficient
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Hierarchical
                            </div>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl cursor-pointer transition-all duration-200 hover:scale-[1.02] border-2 border-gray-200 hover:border-primary/30">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-2 rounded-lg bg-orange-100 text-orange-700 border-orange-300"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-minus h-4 w-4" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                </svg></div>
                            <div>
                                <h3 class="font-medium text-sm">Minimal Style</h3>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mb-3 line-clamp-2">Clean underline-based tabs with
                            minimal styling</p>
                        <div class="space-y-1">
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Clean lines
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Minimal design
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Underline indicators
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground">
                                <div class="w-1 h-1 bg-current rounded-full mr-2"></div>Text-focused
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b">
            <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-panels-top-left h-5 w-5 text-primary" aria-hidden="true">
                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                    <path d="M3 9h18"></path>
                    <path d="M9 21V9"></path>
                </svg>Live Preview: Pills Style</h4>
            <p data-slot="card-description" class="text-muted-foreground">This shows how the pills style would look with
                your Laboratory Settings content</p>
        </div>
        <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
            <div class="space-y-6">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-4 shadow-sm">
                    <div class="flex flex-wrap gap-2"><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-primary text-primary-foreground shadow-lg shadow-primary/25"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-4 w-4" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Test Panels</button><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-settings h-4 w-4" aria-hidden="true">
                                <path
                                    d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915">
                                </path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>Workflow Config</button><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-copy h-4 w-4" aria-hidden="true">
                                <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                                <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                            </svg>API Integration</button><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-database h-4 w-4" aria-hidden="true">
                                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                <path d="M3 12A9 3 0 0 0 21 12"></path>
                            </svg>System Settings</button><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-mail h-4 w-4" aria-hidden="true">
                                <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            </svg>Notifications</button><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-users h-4 w-4" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>User Management</button><button
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-shield h-4 w-4" aria-hidden="true">
                                <path
                                    d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                </path>
                            </svg>Backup &amp; Recovery</button></div>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <div class="flex flex-wrap gap-2"><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Molecular</button><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Hematology</button><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Chemistry</button><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Microbiology</button><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Genetics</button><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Toxicology</button><button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg>Immunology</button></div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Sample Content</h4>
                        <p data-slot="card-description" class="text-muted-foreground">This shows how content appears
                            with pills tab style</p>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold">Molecular Test Groups</h3><button data-slot="button"
                                    class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-plus h-4 w-4 mr-2"
                                        aria-hidden="true">
                                        <path d="M5 12h14"></path>
                                        <path d="M12 5v14"></path>
                                    </svg>Add New Group</button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div data-slot="card"
                                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                                        <div class="flex items-center gap-2 mb-2"><svg
                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-test-tube h-4 w-4 text-primary" aria-hidden="true">
                                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2">
                                                </path>
                                                <path d="M8.5 2h7"></path>
                                                <path d="M14.5 16h-5"></path>
                                            </svg><span class="font-medium">PCR Tests</span></div>
                                        <p class="text-sm text-muted-foreground">DNA amplification tests</p><span
                                            data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-primary text-primary-foreground [a&amp;]:hover:bg-primary/90 mt-2">12
                                            active</span>
                                    </div>
                                </div>
                                <div data-slot="card"
                                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                                        <div class="flex items-center gap-2 mb-2"><svg
                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-database h-4 w-4 text-primary" aria-hidden="true">
                                                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                                <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                                <path d="M3 12A9 3 0 0 0 21 12"></path>
                                            </svg><span class="font-medium">Sequencing</span></div>
                                        <p class="text-sm text-muted-foreground">DNA/RNA sequencing</p><span
                                            data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-primary text-primary-foreground [a&amp;]:hover:bg-primary/90 mt-2">8
                                            active</span>
                                    </div>
                                </div>
                                <div data-slot="card"
                                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                                        <div class="flex items-center gap-2 mb-2"><svg
                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-shield h-4 w-4 text-primary" aria-hidden="true">
                                                <path
                                                    d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                                </path>
                                            </svg><span class="font-medium">Quality Control</span></div>
                                        <p class="text-sm text-muted-foreground">QC and validation</p><span
                                            data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-primary text-primary-foreground [a&amp;]:hover:bg-primary/90 mt-2">3
                                            active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
            <h4 data-slot="card-title" class="leading-none">Style Characteristics</h4>
            <p data-slot="card-description" class="text-muted-foreground">Compare the pros and cons of each tab style
            </p>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-3 font-medium">Style</th>
                            <th class="text-left p-3 font-medium">Best For</th>
                            <th class="text-left p-3 font-medium">Pros</th>
                            <th class="text-left p-3 font-medium">Considerations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium">Current</td>
                            <td class="p-3">Familiar layouts</td>
                            <td class="p-3">Established, functional</td>
                            <td class="p-3">May feel outdated</td>
                        </tr>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium">Pills</td>
                            <td class="p-3">Modern interfaces</td>
                            <td class="p-3">Trendy, animated, compact</td>
                            <td class="p-3">May not scale with many tabs</td>
                        </tr>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium">Cards</td>
                            <td class="p-3">Visual navigation</td>
                            <td class="p-3">Self-explanatory, engaging</td>
                            <td class="p-3">Takes more vertical space</td>
                        </tr>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium">Sidebar</td>
                            <td class="p-3">Complex hierarchies</td>
                            <td class="p-3">Space efficient, organized</td>
                            <td class="p-3">Less content width</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium">Minimal</td>
                            <td class="p-3">Clean interfaces</td>
                            <td class="p-3">Simple, accessible</td>
                            <td class="p-3">Less visual hierarchy</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>