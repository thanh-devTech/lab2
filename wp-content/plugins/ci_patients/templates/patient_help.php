<?php
$ci_patient_options = get_option('trpatient_settings', array());
$phone = $ci_patient_options['help_support_phone'] ?? '';
$email = $ci_patient_options['help_support_email'] ?? '';
$time = $ci_patient_options['help_support_time'] ?? '';

$sql = $wpdb->prepare(
    "
    SELECT  p.ID, p.post_title, p.post_content
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order'
    WHERE p.post_type = %s AND p.post_status = 'publish'
    ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC
    ",
    'help_support'
);
$helps = $wpdb->get_results($sql, ARRAY_A);
if (empty($helps)) {
    sync_help_support_posts(1);
    $helps = $wpdb->get_results($sql, ARRAY_A);
}
?>

<main class="p-6 bg-[#f8f9fa] overflow-auto flex-1">
    <div class="space-y-6">
        <!-- Contact Information Card -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone w-5 h-5 text-[#D83D00]" aria-hidden="true">
                        <path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-[#3C3D59]">Contact Information</h3>
                </div>
            </div>
            <div data-slot="card-content" class="[&:last-child]:pb-6 p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone w-5 h-5 text-[#D83D00] mt-0.5" aria-hidden="true">
                            <path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path>
                        </svg>
                        <div>
                            <div class="text-sm text-gray-500">Phone</div>
                            <div class="font-medium text-gray-900"><?php echo $phone; ?></div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail w-5 h-5 text-[#D83D00] mt-0.5" aria-hidden="true">
                            <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        </svg>
                        <div>
                            <div class="text-sm text-gray-500">Email</div>
                            <div class="font-medium text-gray-900"><?php echo $email; ?></div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar w-5 h-5 text-[#D83D00] mt-0.5" aria-hidden="true">
                            <path d="M8 2v4"></path>
                            <path d="M16 2v4"></path>
                            <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                            <path d="M3 10h18"></path>
                        </svg>
                        <div>
                            <div class="text-sm text-gray-500">Hours</div>
                            <div class="font-medium text-gray-900"><?php echo $time; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Card -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark w-5 h-5 text-[#D83D00]" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-[#3C3D59]">Frequently Asked Questions</h3>
                </div>
            </div>
            <div data-slot="card-content" class="[&:last-child]:pb-6 p-6">
                <div class="space-y-3" id="faq-container">
                    <?php
                    foreach ($helps as $help) :
                    ?>
                        <div class="faq-item" data-state="closed">
                            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border border-gray-200 hover:border-gray-300 transition-all">
                                <button type="button" class="faq-trigger w-full" aria-expanded="false" data-index="<?php echo $help['ID']; ?>">
                                    <div data-slot="card-content" class="[&:last-child]:pb-6 p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex items-start gap-3 text-left flex-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark h-5 w-5 text-[#D83D00] mt-0.5 flex-shrink-0" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                                    <path d="M12 17h.01"></path>
                                                </svg>
                                                <div class="flex-1">
                                                    <h3 class="font-medium text-gray-900"><?php echo $help['post_title']; ?></h3>
                                                </div>
                                            </div>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="faq-chevron lucide lucide-chevron-right h-5 w-5 text-gray-500 flex-shrink-0 transition-transform duration-200" aria-hidden="true">
                                                <path d="m9 18 6-6-6-6"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </button>
                                <div class="faq-content" style="display: none;">
                                    <div class="pl-8 pr-8 pb-4 text-gray-700 text-sm text-gray-600 leading-relaxed">
                                        <div class="pt-3 border-t border-gray-200">
                                            <?php echo apply_filters('the_content', $help['post_content'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    $(document).ready(function() {
        $('.faq-trigger').on('click', function() {
            const $faqItem = $(this).closest('.faq-item');
            const $faqContent = $faqItem.find('.faq-content');
            const $chevron = $faqItem.find('.faq-chevron');
            const isOpen = $faqItem.attr('data-state') === 'open';

            if (isOpen) {
                $faqContent.slideUp(300);
                $faqItem.attr('data-state', 'closed');
                $(this).attr('aria-expanded', 'false');
                $chevron.css('transform', 'rotate(0deg)');
            } else {
                $faqContent.slideDown(300);
                $faqItem.attr('data-state', 'open');
                $(this).attr('aria-expanded', 'true');
                $chevron.css('transform', 'rotate(90deg)');
            }
        });
    });
</script>

<style>
    .faq-chevron {
        transition: transform 0.3s ease-in-out;
    }
</style>