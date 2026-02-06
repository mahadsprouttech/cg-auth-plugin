<?php
/**
 * Plugin Name: Content Gorilla - Auth
 * Description: Used to authenticate your WordPress website with Content Gorilla.
 * Author: Sprout Tech
 * Author URI: www.contentgorilla.co
 * Version: 1.5.7
 * Plugin URI: www.contentgorilla.co
 */


function json_basic_auth_handler($user)
{
    global $wp_json_basic_auth_error;

    $wp_json_basic_auth_error = null;

    // Don't authenticate twice
    if (!empty($user)) {
        return $user;
    }


    // Alternative way
    if (!isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['HTTP_AUTHORIZATION2'])) {
        list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode($_SERVER['HTTP_AUTHORIZATION2']));
    }

    // Check that we're trying to authenticate
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
    } else {
        return $user;
    }

    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    /**
     * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
     * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
     * recursion and a stack overflow unless the current function is removed from the determine_current_user
     * filter during authentication.
     */
    remove_filter('determine_current_user', 'json_basic_auth_handler', 20);

    $user = wp_authenticate($username, $password);

    add_filter('determine_current_user', 'json_basic_auth_handler', 20);

    if (is_wp_error($user)) {
        $wp_json_basic_auth_error = $user;
        return null;
    }

    $wp_json_basic_auth_error = true;

    return $user->ID;
}

add_filter('determine_current_user', 'json_basic_auth_handler', 20);

function json_basic_auth_error($error)
{
    // Passthrough other errors
    if (!empty($error)) {
        return $error;
    }

    // Check if user is already authenticated via another method
    // (e.g., WordPress Application Passwords at priority 10)
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->ID > 0) {
        // User is authenticated, don't return our auth error
        return $error;
    }

    // Only return our error if no authentication succeeded
    global $wp_json_basic_auth_error;

    return $wp_json_basic_auth_error;
}

add_filter('rest_authentication_errors', 'json_basic_auth_error');



if (! class_exists('CgYoutubeData')) {
    include_once(plugin_dir_path(__FILE__) . 'includes/YoutubeData.php');
}

if (! class_exists('WordPressData')) {
    include_once(plugin_dir_path(__FILE__) . 'includes/WordPressData.php');
}

add_action('rest_api_init', function () {
    register_rest_route('api/v1', '/youtube_video_captions_list/', array(
        'methods' => 'GET',
        'callback' => 'cg_get_video_captions_list',
    ));

    register_rest_route('api/v1', '/youtube_video_caption/', array(
        'methods' => 'GET',
        'callback' => 'cg_get_video_caption',
    ));

    register_rest_route('api/v1', '/create_post/', array(
        'methods' => 'POST',
        'callback' => 'cg_create_post',
    ));
});

function cg_get_video_captions_list($request)
{
    $youtube_data = new CgYoutubeData(__FILE__);
    $video_id = $request->get_param('video_id');
    return $youtube_data->getCaptions($video_id);
}

function cg_get_video_caption($request)
{
    $youtube_data = new CgYoutubeData(__FILE__);
    $video_id = $request->get_param('video_id');
    $lang = strtolower($request->get_param('lang'));
    return $youtube_data->getCaption($video_id, $lang);
}

function cg_create_post($request)
{
    $wordpress = new WordPressData(__FILE__);
    $title = $request->get_param('title');
    $status = $request->get_param('status');
    $date = $request->get_param('date');
    $tags = $request->get_param('tags');
    $categories = $request->get_param('categories');
    $interval_hours = $request->get_param('interval_hours');
    $interval_minutes = $request->get_param('interval_minutes');
    $image_url = $request->get_param('image_url');
    $content = $request->get_param('content');
    $author_id = $request->get_param('author_id');
    
    return $wordpress->createPost($title, $status, $date, $tags, $categories, $interval_hours, $interval_minutes, $image_url, $content, $author_id);
}


/**
 * Handle Plugin Updates
 */
if (!class_exists('Wpyoutube_Updater')) {
    include_once(plugin_dir_path(__FILE__) . 'updater.php');
}

$updater = new Wpyoutube_Updater(__FILE__);
$updater->set_username('mahadsprouttech');
$updater->set_repository('cg-auth-plugin');
$updater->initialize();
