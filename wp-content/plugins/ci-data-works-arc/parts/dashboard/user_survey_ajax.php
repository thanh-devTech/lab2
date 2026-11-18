<?php

if (isset($_POST['trajax']) && $_POST['trajax'] === 'submit_user_feedback') {
    $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : [];
    try {
        if (empty($feedback)) {
            wp_send_json([
                'success' => false,
                'error' => "Something went wrong!"
            ]);
            exit;
        }
        $rs = tr_client_send_user_feedback(
            [
                'action' => 'send_user_feedback',
                'feedback' => $feedback,
                'user_email' => $user->user_email
            ]
        );
        if (empty($rs)) {
            wp_send_json([
                'success' => false,
                'error' => "Something went wrong!"
            ]);
            exit;
        }
        if ($rs['success']) {
            $feedback_id = isset($feedback['feedback_id']) ? intval($feedback['feedback_id']) : 0;

            if ($feedback_id > 0) {
                $submitted_feedback_ids = get_user_meta($user->ID, 'submitted_feedback_ids', true);
                $submitted_feedback_ids = !empty($submitted_feedback_ids) ? json_decode($submitted_feedback_ids, true) : [];

                if (!in_array($feedback_id, $submitted_feedback_ids)) {
                    $submitted_feedback_ids[] = $feedback_id;

                    update_user_meta($user->ID, 'submitted_feedback_ids', json_encode($submitted_feedback_ids));
                }
            }
        }

        wp_send_json([
            'success' => $rs['success'],
            'error' => !$rs['success'] ? $rs['message'] ?? "Something went wrong!" : ""
        ]);
        exit;
    } catch (Exception $e) {
        wp_send_json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
