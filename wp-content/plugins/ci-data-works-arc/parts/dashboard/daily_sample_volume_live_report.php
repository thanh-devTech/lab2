<div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
    <h4 class="font-semibold text-base mb-4 text-slate-800" style="margin-bottom: 13px;">Live Daily Volume Analysis Report</h4>

    <!-- Info -->
    <div class="mb-4 p-3 bg-white rounded-lg border">
        <div class="grid grid-cols-4 gap-4 text-xs">
            <div><span class="font-medium">Time Period:</span>
                <div class="text-gray-600"><?php echo esc_html($report_time_label); ?></div>
            </div>
            <div><span class="font-medium">Test Type:</span>
                <div class="text-gray-600"><?php echo strtoupper($btype); ?></div>
            </div>
            <div><span class="font-medium">Panels:</span>
                <div class="text-gray-600"><?php echo esc_html($panel_label); ?></div>
            </div>
            <div><span class="font-medium">Clinic:</span>
                <div class="text-gray-600"><?php echo esc_html($cid_label); ?></div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="mb-4">
        <h5 class="font-medium text-sm mb-2">Daily Volume by Panel Type</h5>
        <div class="bg-white rounded-lg border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium">Date</th>
                            <?php foreach ($panel_distribution as $pd):?>
                                <th class="text-center p-3 font-medium <?php echo $pd['selected']? 'selectedpanel' : '' ?>"><?php echo esc_html($pd['short_name']); ?></th>
                            <?php endforeach; ?>
                            <th class="text-center p-3 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Total -->
                        <tr class="border-t bg-gray-100 font-bold">
                            <td class="p-3">Total</td>
                            <?php foreach ($panel_distribution as $pd): ?>
                                <td class="p-3 text-center <?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo number_format($pd['count']); ?></td>
                            <?php endforeach; ?>
                            <td class="p-3 text-center"><?php echo number_format($summary['total_all_panels']); ?></td>
                        </tr>

                        <!-- % -->
                        <tr class="border-t bg-gray-50 font-medium">
                            <td class="p-3">%</td>
                            <?php foreach ($panel_distribution as $pd): ?>
                                <td class="p-3 text-center <?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo $pd['percent']; ?>%</td>
                            <?php endforeach; ?>
                            <td class="p-3 text-center">100%</td>
                        </tr>

                        <!-- Daily Avg -->
                        <tr class="border-t bg-blue-50 font-medium text-blue-700">
                            <td class="p-3">Daily Avg</td>
                            <?php foreach ($panel_distribution as $pd): ?>
                                <td class="p-3 text-center <?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo $pd['daily_avg']; ?></td>
                            <?php endforeach; ?>
                            <td class="p-3 text-center"><?php echo $summary['average_daily_all_panel']; ?></td>
                        </tr>

                        <!-- Daily rows -->
                        <?php foreach ($daily_panel_rows as $row): ?>
                            <tr class="border-t">
                                <td class="p-3 font-medium"><?php echo $row['date']; ?></td>
                                <?php foreach ($panel_distribution as $pd): ?>
                                    <td class="p-3 text-center <?php echo $pd['selected']  ? 'selectedpanel' : '' ?>">
                                        <?php echo $row['panels'][$pd['short_name']] ?? 0; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="p-3 text-center font-medium"><?php echo $row['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Note -->
    <div class="text-xs text-slate-600 bg-slate-100 p-2 rounded">
        💡 This report updates automatically when you change filters above.
        Use the Export button for PDF/CSV versions.
    </div>
</div>