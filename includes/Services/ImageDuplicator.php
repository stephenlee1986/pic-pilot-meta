<?php

namespace PicPilotMeta\Services;

use PicPilotMeta\Helpers\PromptManager;
use WP_Post;

defined('ABSPATH') || exit;

class ImageDuplicator {

    /**
     * Duplicates an attachment image and returns the new attachment ID.
     *
     * @param int $attachment_id
     * @param string|null $new_title
     * @param string|null $new_filename
     * @param string|null $new_alt
     * @return int|null
     */
    public static function duplicate(int $attachment_id, ?string $new_title = null, ?string $new_filename = null, ?string $new_alt = null): ?int {
        $original = get_post($attachment_id);

        if (!$original instanceof WP_Post || 'attachment' !== $original->post_type) {
            return null;
        }

        $file = get_attached_file($attachment_id);
        if (!file_exists($file)) {
            return null;
        }

        $pathinfo = pathinfo($file);
        $extension = $pathinfo['extension'];

        // Use user-defined filename or generate a default
        $base_filename = $new_filename ?: $pathinfo['filename'] . PromptManager::getCopySuffix('filename');
        $unique_filename = wp_unique_filename($pathinfo['dirname'], $base_filename . '.' . $extension);
        $new_filepath = $pathinfo['dirname'] . '/' . $unique_filename;

        if (!copy($file, $new_filepath)) {
            return null;
        }

        // Prepare attachment data
        $attachment = [
            'post_mime_type' => $original->post_mime_type,
            'post_title'     => $new_title ? $new_title : $original->post_title . PromptManager::getCopySuffix('title'),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => 0,
        ];

        $attach_id = wp_insert_attachment($attachment, $new_filepath);
        if (is_wp_error($attach_id)) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $new_filepath));

        // Handle alt text - use new_alt if provided, otherwise copy from original
        if ($new_alt) {
            update_post_meta($attach_id, '_wp_attachment_image_alt', $new_alt);
        } else {
            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text . PromptManager::getCopySuffix('alt'));
            }
        }

        // Track origin
        update_post_meta($attach_id, '_is_variant_of', $attachment_id);

        return $attach_id;
    }
}
