<div class="flex-1 overflow-auto p-8">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Test Panel Configuration</h1>
                <p class="text-muted-foreground mt-1 text-[13px]">Manage test categories, settings, and configurations
                </p>
            </div>
            <div class="flex items-center gap-3"><button data-slot="button"
                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-card hover:shadow-card-hover transition-shadow-smooth"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-download h-5 w-5 mr-2" aria-hidden="true">
                        <path d="M12 15V3"></path>
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <path d="m7 10 5 5 5-5"></path>
                    </svg>Export All</button><button data-slot="button"
                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-card hover:shadow-card-hover transition-shadow-smooth"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-upload h-5 w-5 mr-2" aria-hidden="true">
                        <path d="M12 3v12"></path>
                        <path d="m17 8-5-5-5 5"></path>
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    </svg>Import Settings</button></div>
        </div>
        <div class="bg-white border rounded-lg px-6 py-3 shadow-sm">
            <nav class="flex items-center space-x-2 text-sm">
                <div class="flex items-center gap-1 text-muted-foreground"><svg xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house h-3 w-3"
                        aria-hidden="true">
                        <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                        <path
                            d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                        </path>
                    </svg>Dashboard</div><span class="text-muted-foreground">/</span>
                <div class="flex items-center gap-1 text-muted-foreground"><svg xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings h-3 w-3"
                        aria-hidden="true">
                        <path
                            d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915">
                        </path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>Settings</div><span class="text-muted-foreground">/</span>
                <div class="flex items-center gap-1 text-muted-foreground"><svg xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings h-3 w-3"
                        aria-hidden="true">
                        <path
                            d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915">
                        </path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>Laboratory Settings</div><span class="text-muted-foreground">/</span>
                <div class="flex items-center gap-1 font-medium"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-test-tube h-3 w-3"
                        aria-hidden="true">
                        <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                        <path d="M8.5 2h7"></path>
                        <path d="M14.5 16h-5"></path>
                    </svg>Test Panels</div>
            </nav>
        </div>
        <div class="space-y-3">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-3 shadow-sm">
                <div class="flex flex-wrap gap-2"><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-primary text-primary-foreground shadow-lg shadow-primary/25"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-shield h-4 w-4" aria-hidden="true">
                            <path
                                d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                            </path>
                        </svg>Tox</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-droplets h-4 w-4" aria-hidden="true">
                            <path
                                d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z">
                            </path>
                            <path
                                d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97">
                            </path>
                        </svg>Blood</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-dna h-4 w-4" aria-hidden="true">
                            <path d="m10 16 1.5 1.5"></path>
                            <path d="m14 8-1.5-1.5"></path>
                            <path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993"></path>
                            <path d="m16.5 10.5 1 1"></path>
                            <path d="m17 6-2.891-2.891"></path>
                            <path d="M2 15c6.667-6 13.333 0 20-6"></path>
                            <path d="m20 9 .891.891"></path>
                            <path d="M3.109 14.109 4 15"></path>
                            <path d="m6.5 12.5 1 1"></path>
                            <path d="m7 18 2.891 2.891"></path>
                            <path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993"></path>
                        </svg>PCR</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-microscope h-4 w-4" aria-hidden="true">
                            <path d="M6 18h8"></path>
                            <path d="M3 22h18"></path>
                            <path d="M14 22a7 7 0 1 0 0-14h-1"></path>
                            <path d="M9 14h2"></path>
                            <path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"></path>
                            <path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"></path>
                        </svg>PGx</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-graduation-cap h-4 w-4" aria-hidden="true">
                            <path
                                d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z">
                            </path>
                            <path d="M22 10v6"></path>
                            <path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"></path>
                        </svg>Study</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-dna h-4 w-4" aria-hidden="true">
                            <path d="m10 16 1.5 1.5"></path>
                            <path d="m14 8-1.5-1.5"></path>
                            <path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993"></path>
                            <path d="m16.5 10.5 1 1"></path>
                            <path d="m17 6-2.891-2.891"></path>
                            <path d="M2 15c6.667-6 13.333 0 20-6"></path>
                            <path d="m20 9 .891.891"></path>
                            <path d="M3.109 14.109 4 15"></path>
                            <path d="m6.5 12.5 1 1"></path>
                            <path d="m7 18 2.891 2.891"></path>
                            <path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993"></path>
                        </svg>Genetics</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-pill h-4 w-4" aria-hidden="true">
                            <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"></path>
                            <path d="m8.5 8.5 7 7"></path>
                        </svg>Drugs</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-beaker h-4 w-4" aria-hidden="true">
                            <path d="M4.5 3h15"></path>
                            <path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"></path>
                            <path d="M6 14h12"></path>
                        </svg>Chemistry</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-zap h-4 w-4" aria-hidden="true">
                            <path
                                d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z">
                            </path>
                        </svg>Metabolites</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-package h-4 w-4" aria-hidden="true">
                            <path
                                d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                            </path>
                            <path d="M12 22V12"></path>
                            <polyline points="3.29 7 12 12 20.71 7"></polyline>
                            <path d="m7.5 4.27 9 5.15"></path>
                        </svg>Receiving</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-ellipsis h-4 w-4" aria-hidden="true">
                            <circle cx="12" cy="12" r="1"></circle>
                            <circle cx="19" cy="12" r="1"></circle>
                            <circle cx="5" cy="12" r="1"></circle>
                        </svg>Other</button></div>
            </div>
            <div class="bg-white rounded-xl border shadow-sm p-3">
                <div class="flex flex-wrap gap-2"><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border bg-primary/10 text-primary border-primary/20 font-medium"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                            <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                            <path d="M8.5 2h7"></path>
                            <path d="M14.5 16h-5"></path>
                        </svg>Tests</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-users h-3.5 w-3.5" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>Groups</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chart-column h-3.5 w-3.5" aria-hidden="true">
                            <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                            <path d="M18 17V9"></path>
                            <path d="M13 17V5"></path>
                            <path d="M8 17v-3"></path>
                        </svg>Batch-Status</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-file-code h-3.5 w-3.5" aria-hidden="true">
                            <path d="M10 12.5 8 15l2 2.5"></path>
                            <path d="m14 12.5 2 2.5-2 2.5"></path>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path>
                        </svg>Diagnostic Codes</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-test-tube h-3.5 w-3.5" aria-hidden="true">
                            <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                            <path d="M8.5 2h7"></path>
                            <path d="M14.5 16h-5"></path>
                        </svg>Specimens</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-circle-question-mark h-3.5 w-3.5" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                            <path d="M12 17h.01"></path>
                        </svg>Questions</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-settings h-3.5 w-3.5" aria-hidden="true">
                            <path
                                d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915">
                            </path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>Req Settings</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-settings h-3.5 w-3.5" aria-hidden="true">
                            <path
                                d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915">
                            </path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>Result Settings</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-triangle-alert h-3.5 w-3.5" aria-hidden="true">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>Warn Pop-up</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-file-text h-3.5 w-3.5" aria-hidden="true">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                            <path d="M10 9H8"></path>
                            <path d="M16 13H8"></path>
                            <path d="M16 17H8"></path>
                        </svg>PDF &amp; Layout</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-activity h-3.5 w-3.5" aria-hidden="true">
                            <path
                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                            </path>
                        </svg>Theoretical</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-shield h-3.5 w-3.5" aria-hidden="true">
                            <path
                                d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                            </path>
                        </svg>Antibiotics</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-bug h-3.5 w-3.5" aria-hidden="true">
                            <path d="M12 20v-9"></path>
                            <path d="M14 7a4 4 0 0 1 4 4v3a6 6 0 0 1-12 0v-3a4 4 0 0 1 4-4z"></path>
                            <path d="M14.12 3.88 16 2"></path>
                            <path d="M21 21a4 4 0 0 0-3.81-4"></path>
                            <path d="M21 5a4 4 0 0 1-3.55 3.97"></path>
                            <path d="M22 13h-4"></path>
                            <path d="M3 21a4 4 0 0 1 3.81-4"></path>
                            <path d="M3 5a4 4 0 0 0 3.55 3.97"></path>
                            <path d="M6 13H2"></path>
                            <path d="m8 2 1.88 1.88"></path>
                            <path d="M9 7.13V6a3 3 0 1 1 6 0v1.13"></path>
                        </svg>Pathogens</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-database h-3.5 w-3.5" aria-hidden="true">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                        </svg>BIOMIC Messages</button><button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm hover:bg-primary/10 hover:text-primary border border-transparent bg-gray-50 text-gray-600 hover:border-primary/20"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-arrow-up-down h-3.5 w-3.5" aria-hidden="true">
                            <path d="m21 16-4 4-4-4"></path>
                            <path d="M17 20V4"></path>
                            <path d="m3 8 4-4 4 4"></path>
                            <path d="M7 4v16"></path>
                        </svg>Data Management</button></div>
            </div>
        </div>
        <div class="space-y-6">
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-l-4 border-l-primary">
                <div data-slot="card-header"
                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-test-tube h-6 w-6 text-primary" aria-hidden="true">
                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                <path d="M8.5 2h7"></path>
                                <path d="M14.5 16h-5"></path>
                            </svg></div>
                        <div>
                            <div class="flex items-center gap-2">Tests<span data-slot="badge"
                                    class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">TOX</span>
                            </div>
                        </div>
                    </h4>
                    <p data-slot="card-description" class="text-muted-foreground">Create and configure laboratory tests
                    </p>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div data-slot="card"
                                class="text-card-foreground flex flex-col gap-6 rounded-xl border p-4 bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-blue-500 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-activity h-4 w-4 text-white" aria-hidden="true">
                                            <path
                                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                                            </path>
                                        </svg></div>
                                    <div>
                                        <div class="font-semibold text-blue-900">Active Tests</div>
                                        <div class="text-2xl font-bold text-blue-700">24</div>
                                    </div>
                                </div>
                            </div>
                            <div data-slot="card"
                                class="text-card-foreground flex flex-col gap-6 rounded-xl border p-4 bg-gradient-to-br from-green-50 to-green-100 border-green-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-green-500 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-test-tube h-4 w-4 text-white" aria-hidden="true">
                                            <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2">
                                            </path>
                                            <path d="M8.5 2h7"></path>
                                            <path d="M14.5 16h-5"></path>
                                        </svg></div>
                                    <div>
                                        <div class="font-semibold text-green-900">Configured</div>
                                        <div class="text-2xl font-bold text-green-700">156</div>
                                    </div>
                                </div>
                            </div>
                            <div data-slot="card"
                                class="text-card-foreground flex flex-col gap-6 rounded-xl border p-4 bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-orange-500 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-triangle-alert h-4 w-4 text-white" aria-hidden="true">
                                            <path
                                                d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
                                            </path>
                                            <path d="M12 9v4"></path>
                                            <path d="M12 17h.01"></path>
                                        </svg></div>
                                    <div>
                                        <div class="font-semibold text-orange-900">Pending</div>
                                        <div class="text-2xl font-bold text-orange-700">8</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pt-4 border-t"><button data-slot="button"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-plus h-4 w-4 mr-2" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="M12 5v14"></path>
                                </svg>Configure New</button><button data-slot="button"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2"
                                    aria-hidden="true">
                                    <path d="M12 15V3"></path>
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <path d="m7 10 5 5 5-5"></path>
                                </svg>Export Settings</button><button data-slot="button"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-upload h-4 w-4 mr-2"
                                    aria-hidden="true">
                                    <path d="M12 3v12"></path>
                                    <path d="m17 8-5-5-5 5"></path>
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                </svg>Import Settings</button></div>
                        <div class="p-6 bg-muted/30 rounded-lg border-2 border-dashed border-muted-foreground/20">
                            <div class="text-center"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-test-tube h-12 w-12 mx-auto text-muted-foreground mb-4"
                                    aria-hidden="true">
                                    <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                    <path d="M8.5 2h7"></path>
                                    <path d="M14.5 16h-5"></path>
                                </svg>
                                <h3 class="font-semibold text-muted-foreground mb-2">Tests Configuration</h3>
                                <p class="text-sm text-muted-foreground">Detailed configuration interface for tox
                                    category will be displayed here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>