<div id="box_6_month_volume" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card mb-8">
    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up h-5 w-5 text-primary" aria-hidden="true">
                <path d="M16 7h6v6"></path>
                <path d="m22 7-8.5 8.5-5-5L2 17"></path>
            </svg>6-Month Volume Trend Data</h4>
        <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Detailed monthly breakdown of samples and positivity rates</p>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 px-3 bg-gray-50">Month</th>
                        <th class="text-center py-2 px-3 bg-gray-50">Total Samples</th>
                        <th class="text-center py-2 px-3 bg-gray-50">Negative</th>
                        <th class="text-center py-2 px-3 bg-gray-50">Positive</th>
                        <th class="text-center py-2 px-3 bg-gray-50">Positivity Rate</th>
                    </tr>
                </thead>
                <tbody id="table_6_month_volume"></tbody>
            </table>
        </div>
    </div>
</div>