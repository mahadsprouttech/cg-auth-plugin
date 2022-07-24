<?php

class WordPressData
{
    public function createPost(
        $title,
        $status,
        $date,
        $tags,
        $categories,
        $interval_hours,
        $interval_minutes,
        $image_url,
        $content,
        $author_id
    )
    {


        if ($interval_hours == 0 and $interval_minutes == 0) {
            $status = 'publish';
            $date = date('Y-m-d H:i:s', time());
        } else {
            $status = 'future';
            $hours = $interval_hours * 60 * 60;
            $minutes = $interval_minutes * 60;
            $date = date('Y-m-d H:i:s', time() + (($hours + $minutes) * $post_number));
        }

        //return 'Hours:' . $interval_hours . ' Minutes: ' . $interval_minutes . ' Post Number: ' . $post_number . ' New Date: '. $date . ' Actual Date: '. $date = date('Y-m-d H:i:s', time());
        $post_id = wp_insert_post(
            array(
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_author' => $author_id,
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => $status,
                'post_type' => 'post',
                'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($date)),
                'post_date' => $date,
                'edit_date' => 'true'
            ));

        if ($tags) {
            wp_set_post_tags($post_id, $tags, false);
        }

        if ($categories) {
            wp_set_post_categories($post_id, $categories, false);
        }

        $this->Generate_Featured_Image($image_url, $post_id);

        return $post_id;
    }


    static function Generate_Featured_Image($image_url, $post_id)
    {
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        if (wp_mkdir_p($upload_dir['path']))
            $file = $upload_dir['path'] . '/' . $post_id . '_' . $filename;
        else
            $file = $upload_dir['basedir'] . '/' . $post_id . '_' . $filename;
        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        $res1 = wp_update_attachment_metadata($attach_id, $attach_data);
        $res2 = set_post_thumbnail($post_id, $attach_id);
    }
}