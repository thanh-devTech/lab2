<?php

if (isset($_GET['debug'])) {
    delete_user_meta(get_current_user_id(), 'submitted_feedback_ids');
}

$submitted_feedback_ids = [];
if ($user->ID) {
    $submitted_query = $wpdb->prepare(
        "SELECT DISTINCT meta_value 
        FROM {$wpdb->usermeta} 
        WHERE user_id = %d 
        AND meta_key = 'submitted_feedback_ids'",
        $user->ID
    );
    $submitted_result = $wpdb->get_var($submitted_query);
    if ($submitted_result) {
        $submitted_feedback_ids = json_decode($submitted_result, true) ?: [];
    }
}

// Build WHERE clause to exclude submitted feedbacks
$exclude_clause = '';
if (!empty($submitted_feedback_ids)) {
    $placeholders = implode(',', array_fill(0, count($submitted_feedback_ids), '%d'));
    $exclude_clause = $wpdb->prepare(" AND p.ID NOT IN ($placeholders)", ...$submitted_feedback_ids);
}

$current_date = date_i18n('Y-m-d');

$sql = $wpdb->prepare(
    "
    SELECT 
        p.ID,
        p.post_title,
        p.post_content,
        mq.meta_value AS feedback_questions,
        mh.meta_value AS hub_post_ID,
        ms.meta_value AS feedback_subtitle,
        msd.meta_value AS feedback_start_date,
        med.meta_value AS feedback_end_date,
        mty.meta_value AS feedback_thank_you
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} mq
        ON p.ID = mq.post_id
       AND mq.meta_key = '_feedback_questions'
    LEFT JOIN {$wpdb->postmeta} ms
        ON p.ID = ms.post_id
       AND ms.meta_key = '_feedback_subtitle'
    LEFT JOIN {$wpdb->postmeta} mh
        ON p.ID = mh.post_id
       AND mh.meta_key = '_hub_post_ID'
    LEFT JOIN {$wpdb->postmeta} msd
        ON p.ID = msd.post_id
       AND msd.meta_key = '_feedback_start_date'
    LEFT JOIN {$wpdb->postmeta} med
        ON p.ID = med.post_id
       AND med.meta_key = '_feedback_end_date'
    LEFT JOIN {$wpdb->postmeta} mty
        ON p.ID = mty.post_id
       AND mty.meta_key = '_feedback_thank_you'
    WHERE p.post_type = %s
      AND p.post_status = 'publish'
      AND (msd.meta_value IS NULL or msd.meta_value ='' OR msd.meta_value <= %s)
      AND (med.meta_value IS NULL or med.meta_value ='' OR med.meta_value >= %s)
      $exclude_clause
    ORDER BY p.post_date DESC
    ",
    'user_feedback',
    $current_date,
    $current_date
);

$user_feedbacks = $wpdb->get_results($sql, ARRAY_A);


if (!empty($user_feedbacks)) {
?>

    <style>
        h2.text-2xl {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .hover\:text-gray-600:hover {
            color: var(--color-gray-600);
        }

        .feedback-item-inner {
            margin-bottom: 1rem;
        }

        .feedback-item-inner:last-child {
            margin-bottom: 0;
        }
    </style>
    <script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>

    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card feedbacks-container">
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
            <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square h-5 w-5 text-primary" aria-hidden="true">
                    <path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"></path>
                </svg>
                igniteLIS User Feedback
            </h4>
            <p data-slot="card-description" class="text-muted-foreground">2-minute survey → shapes what we build next.</p>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
            <div class="space-y-4">
                <?php foreach ($user_feedbacks as $user_feedback) {
                    $feedback_id = $user_feedback['ID'];
                    $questions = !empty($user_feedback['feedback_questions']) ? json_decode($user_feedback['feedback_questions'], true) : [];
                    $thank_you_message = !empty($user_feedback['feedback_thank_you']) ? $user_feedback['feedback_thank_you'] : '<p>Your feedback is in! Thank you for helping us improve igniteLIS. We\'re already putting it to work.</p><br><p style="color: #6a7282">🎁 Gift card winners will be contacted within two weeks.</p>';
                    $thank_you_message = apply_filters('the_content', $thank_you_message);
                ?>

                    <div class="feedback-item-inner feedback-item-<?php echo $feedback_id; ?>" data-feedback-id="<?php echo $feedback_id; ?>">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <div class="flex items-start gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-5 w-5 text-[#D83D00] flex-shrink-0 mt-0.5" aria-hidden="true">
                                    <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
                                    <path d="M20 2v4"></path>
                                    <path d="M22 4h-4"></path>
                                    <circle cx="4" cy="20" r="2"></circle>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-700"><strong><?php echo esc_html($user_feedback['post_title']); ?></strong> <?php echo esc_html($user_feedback['feedback_subtitle']); ?></p>
                                    <p class="text-sm text-gray-700 mt-2"><?php echo esc_html($user_feedback['post_content']); ?></p>
                                </div>
                            </div>
                        </div>
                        <button data-feedback-id="<?php echo $feedback_id; ?>" data-slot="button" class="inline-flex open-survey-btn mt-3 items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 w-full">
                            Take Survey
                        </button>

                        <!-- Survey Modal -->
                        <div id="survey-modal-overlay-<?php echo $feedback_id; ?>" class="survey-modal-overlay fixed inset-0 bg-black/50 items-center justify-center p-4 overflow-y-auto" style="display: none; z-index: 667;">
                            <div class="survey-modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full my-8 max-h-[90vh] flex flex-col">
                                <div class="border-b border-gray-200 p-6 flex items-start justify-between bg-white rounded-lg flex-shrink-0">
                                    <div class="flex-1 pr-4">
                                        <h2 class="text-2xl font-bold text-[#3C3D59] mb-2">igniteLIS User Feedback</h2>
                                        <p class="text-sm text-gray-600">2-minute survey → shapes what we build next.</p>
                                    </div>
                                    <button class="survey-close-btn text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0" data-feedback-id="<?php echo $feedback_id; ?>" aria-label="Close">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-6 w-6" aria-hidden="true">
                                            <path d="M18 6 6 18"></path>
                                            <path d="m6 6 12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-6 overflow-y-auto flex-1 wrap-form-survey">
                                    <form class="survey-form space-y-6" data-feedback-id="<?php echo $feedback_id; ?>" data-thank-you-message="<?php echo esc_attr($thank_you_message); ?>">
                                        <input type="hidden" name="post_ID" value="<?php echo $user_feedback['hub_post_ID']; ?>">
                                        <input type="hidden" name="feedback_id" value="<?php echo $feedback_id; ?>">
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <p class="text-xs text-gray-600"><strong>Confidentiality:</strong> All survey responses are confidential and will only be used for product development purposes. Your feedback helps us build better tools for laboratory professionals.</p>
                                        </div>
                                        <?php
                                        if (!empty($questions)) {
                                            foreach ($questions as $index => $question) {
                                                $question_number = $index + 1;
                                                $name = isset($question['name']) ? $question['name'] : 'question_' . $index;
                                                $label = isset($question['label']) ? $question['label'] : '';
                                                $type = isset($question['type']) ? $question['type'] : 'text';
                                                $required = isset($question['required']) && $question['required'] ? true : false;
                                                $options = isset($question['options']) ? $question['options'] : [];

                                                echo '<div>';
                                                echo '<label class="block text-sm font-semibold text-gray-700 mb-3">';
                                                echo esc_html($question_number . ') ' . $label);
                                                if ($required && $type !== 'checkbox_group') {
                                                    echo ' <span class="text-red-500">*</span>';
                                                }
                                                echo '</label>';

                                                // Render based on type
                                                switch ($type) {
                                                    case 'text':
                                                        echo '<input type="text" ';
                                                        echo 'name="' . esc_attr($name) . '" ';
                                                        echo 'class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-[#D83D00] focus:border-[#D83D00]" ';
                                                        echo 'placeholder="Your answer..." ';
                                                        if ($required) echo 'required ';
                                                        echo '/>';
                                                        break;

                                                    case 'textarea':
                                                        echo '<textarea ';
                                                        echo 'name="' . esc_attr($name) . '" ';
                                                        echo 'placeholder="Your answer..." ';
                                                        echo 'rows="4" ';
                                                        echo 'class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-[#D83D00] focus:border-[#D83D00]" ';
                                                        if ($required) echo 'required ';
                                                        echo '></textarea>';
                                                        break;

                                                    case 'checkbox':
                                                        echo '<label class="flex items-center gap-2 cursor-pointer">';
                                                        echo '<input type="checkbox" ';
                                                        echo 'name="' . esc_attr($name) . '" ';
                                                        echo 'value="1" ';
                                                        echo 'class="w-4 h-4 text-[#D83D00] focus:ring-[#D83D00] rounded" ';
                                                        if ($required) echo 'required ';
                                                        echo '/>';
                                                        echo '<span class="text-sm text-gray-700">' . esc_html($label) . '</span>';
                                                        echo '</label>';
                                                        break;

                                                    case 'radio':
                                                        if (!empty($options)) {
                                                            echo '<div class="space-y-2">';
                                                            foreach ($options as $option) {
                                                                echo '<label class="flex items-center gap-2 cursor-pointer">';
                                                                echo '<input type="radio" ';
                                                                echo 'name="' . esc_attr($name) . '" ';
                                                                echo 'class="w-4 h-4 text-[#D83D00] focus:ring-[#D83D00]" ';
                                                                if ($required) echo 'required ';
                                                                echo 'value="' . esc_attr($option) . '">';
                                                                echo '<span class="text-sm text-gray-700">' . esc_html($option) . '</span>';
                                                                echo '</label>';
                                                            }
                                                            echo '</div>';
                                                        }
                                                        break;

                                                    case 'checkbox_group':
                                                        if (!empty($options)) {
                                                            echo '<div class="space-y-2">';
                                                            foreach ($options as $opt_index => $option) {
                                                                echo '<label class="flex items-center gap-2 cursor-pointer">';
                                                                echo '<input type="checkbox" ';
                                                                echo 'name="' . esc_attr($name) . '[]" ';
                                                                echo 'value="' . esc_attr($option) . '" ';
                                                                echo 'class="w-4 h-4 text-[#D83D00] focus:ring-[#D83D00] rounded">';
                                                                echo '<span class="text-sm text-gray-700">' . esc_html($option) . '</span>';
                                                                echo '</label>';
                                                            }
                                                            echo '</div>';
                                                        }
                                                        break;
                                                }

                                                echo '</div>';
                                            }
                                        }
                                        ?>
                                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                                            <button data-feedback-id="<?php echo $feedback_id; ?>" data-slot="button" class="survey-submit-btn inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-primary/90 text-white px-6" type="submit">
                                                Submit Survey
                                            </button>
                                            <button data-feedback-id="<?php echo $feedback_id; ?>" data-slot="button" class="survey-cancel-btn inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 border-gray-300 text-gray-700 hover:bg-gray-50" type="button">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php } ?>
            </div>

            <!-- Success Modal (Shared) -->
            <div id="success-modal-overlay" class="fixed inset-0 bg-black/50 items-center justify-center p-4" style="display: none; z-index: 668;">
                <div class="bg-white rounded-lg shadow-xl w-full p-8 text-center" style="max-width: 396px;">
                    <div class="mb-4">
                        <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-[#3C3D59] mb-2">Thank You!</h2>
                        <div id="success-message-content" class="text-gray-600"></div>
                    </div>
                    <button id="success-cancel-btn" data-slot="button" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-primary/90 text-white">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(function($) {
            const $successModalOverlay = $('#success-modal-overlay');
            const $successCancelBtn = $('#success-cancel-btn');

            $('.open-survey-btn').on('click', function(e) {
                e.preventDefault();
                const feedbackId = $(this).data('feedback-id');
                const $modalOverlay = $('#survey-modal-overlay-' + feedbackId);
                $modalOverlay.fadeIn(200).css('display', 'flex');
                $('body').addClass('overflow-hidden');
            });

            function closeModal(feedbackId) {
                const $modalOverlay = $('#survey-modal-overlay-' + feedbackId);
                $modalOverlay.fadeOut(200);
                $('body').removeClass('overflow-hidden');
                $modalOverlay.find('.wrap-form-survey').scrollTop(0);
            }

            $('.survey-close-btn').on('click', function() {
                const feedbackId = $(this).data('feedback-id');
                closeModal(feedbackId);
            });

            $('.survey-cancel-btn').on('click', function() {
                const feedbackId = $(this).data('feedback-id');
                closeModal(feedbackId);
            });

            $successCancelBtn.on('click', function() {
                $successModalOverlay.fadeOut(200);
                $('body').removeClass('overflow-hidden');
                if ($('.feedbacks-container .feedback-item-inner').length === 0) {
                    $('.feedbacks-container').remove();
                }
            });

            $('.survey-modal-overlay').on('click', function(e) {
                if ($(e.target).hasClass('survey-modal-overlay')) {
                    const feedbackId = $(this).attr('id').replace('survey-modal-overlay-', '');
                    closeModal(feedbackId);
                }
            });

            $(document).on('keydown', function(e) {
                if (e.key === "Escape") {
                    $('.survey-modal-overlay:visible').each(function() {
                        const feedbackId = $(this).attr('id').replace('survey-modal-overlay-', '');
                        closeModal(feedbackId);
                    });
                }
            });

            // Submit survey form
            $('.survey-form').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const feedbackId = $form.data('feedback-id');
                const $submitBtn = $('.survey-submit-btn[data-feedback-id="' + feedbackId + '"]');

                const formData = {};
                $form.serializeArray().forEach(function(field) {
                    if (field.name.endsWith('[]')) {
                        const key = field.name.replace('[]', '');
                        if (!formData[key]) {
                            formData[key] = [];
                        }
                        formData[key].push(field.value);
                    } else {
                        formData[field.name] = field.value;
                    }
                });

                $submitBtn.prop('disabled', true).text('Submitting...');

                $.ajax({
                    type: 'POST',
                    data: {
                        trajax: 'submit_user_feedback',
                        feedback: formData,
                    },
                    success: function(res) {
                        if (res.success) {
                            $('.feedback-item-' + feedbackId).fadeOut(2000, function() {
                                $(this).remove();
                            });

                            const thankYouMessage = $form.data('thank-you-message');
                            const defaultMessage = '<p>Your feedback is in! Thank you for helping us improve igniteLIS. We\'re already putting it to work.</p><br><p style="color: #6a7282">🎁 Gift card winners will be contacted within two weeks.</p>';
                            $('#success-message-content').html(thankYouMessage || defaultMessage);

                            $('body').addClass('overflow-hidden');
                            $successModalOverlay.fadeIn(200).css('display', 'flex');

                            $form[0].reset();
                            closeModal(feedbackId);
                        } else {
                            showToast('error', 'Survey', res.error || 'Failed to send survey', 10000, 'top_center');
                        }
                        $submitBtn.prop('disabled', false).text('Submit Survey');
                    },
                    error: function(xhr, status, err) {
                        showToast('error', 'Survey', err, 3000, 'top_center');
                        $submitBtn.prop('disabled', false).text('Submit Survey');
                    }
                });
            });
        });
    </script>

<?php } ?>