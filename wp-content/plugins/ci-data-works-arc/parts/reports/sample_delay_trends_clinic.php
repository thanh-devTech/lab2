<div id="box_clinic" data-slot="card" class="bg-card text-card-foreground flex flex-col rounded-xl border shadow-card mt-8 gap-2">
    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-2">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex-1">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-5 w-5 text-[#D83D00]" aria-hidden="true">
                        <path d="M10 12h4"></path>
                        <path d="M10 8h4"></path>
                        <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                        <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                        <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                    </svg>Clinic Pre-Lab Performance by Time Bracket</h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Selected month and 12-month average performance by clinic</p>
            </div>
            <div class="clinic-month-filter flex items-center gap-2 lg:justify-end lg:flex-nowrap">
                <label for="sel_clinic_month" class="text-sm font-medium text-gray-700 whitespace-nowrap">Select month</label>
                <select id="sel_clinic_month" class="h-9 min-w-[110px] rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-[#D83D00] focus:outline-none focus:ring-2 focus:ring-[#D83D00]/15">
                    <option value="">--</option>
                </select>
            </div>
        </div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-2">
        <div class="overflow-x-auto">
            <table id="table_clinic" class="w-full border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-200">
                        <th class="uppercase py-3 px-4 text-left">
                            <button class="flex items-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none">Clinic 
                                <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">&gt;72h
                                <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">48-72h
                                <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">24-48h
                            <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">12-24h
                                <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">&lt;12h
                                <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">Under 24h Target (<span class="clinic_selected_month_text">--</span>)
                            <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th class="uppercase py-3 px-4 text-center">
                            <button class="flex items-center justify-center gap-1 text-sm font-medium text-gray-900 hover:text-gray-700 cursor-pointer select-none w-full">12-Month Avg
                                <?php echo icon_sort(); ?>
                            </button></th>
                    </tr>
                </thead>
                <tbody id="all_clinic"></tbody>
            </table>
        </div>
    </div>
</div>
