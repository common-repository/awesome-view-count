<?php
/*
Plugin Name: Awesome View Count
Plugin URI: https://wordpress.org/plugins/awesome-testimonials/
Description: A powerful WordPress Plugin designed to showcase the number of times your posts or page have been viewed by your visitors. 
Version: 2.0.1
Author: Prakash Parghi
Author URI: https://spotarrow.com/
License: GPL
*/


// Add custom column to posts and pages table
function avc_posts_columns($columns) {
    $columns['post_views'] = __('Views', 'text-domain');
    return $columns;
}
add_filter('manage_posts_columns', 'avc_posts_columns');
add_filter('manage_pages_columns', 'avc_posts_columns');

// Display post view count in custom column
function avc_post_column_content($column_name, $post_id) {
    if ($column_name == 'post_views') {
        $views = get_post_meta($post_id, 'avc_count', true);
        echo esc_html($views ? $views : '0');
    }
}
add_action('manage_posts_custom_column', 'avc_post_column_content', 10, 2);
add_action('manage_pages_custom_column', 'avc_post_column_content', 10, 2);

// Add sorting option for post and page views column
function avc_sortable_columns($columns) {
    $columns['post_views'] = 'post_views';
    return $columns;
}
add_filter('manage_edit-post_sortable_columns', 'avc_sortable_columns');
add_filter('manage_edit-page_sortable_columns', 'avc_sortable_columns');

// Handle sorting query for post and page views columns
function avc_sort_views_column($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ('post_views' == $orderby) {
        $query->set('meta_key', 'avc_count');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'avc_sort_views_column');

function enqueue_custom_files() {
    // Enqueue your custom CSS file for the admin area
    wp_enqueue_style('custom-media-css', plugins_url('/css/awesome.css', __FILE__), false, '1.0', 'all');
    wp_enqueue_script('canvasjs', plugins_url('/js/canvasjs.min.js', __FILE__));
}


// Hook into the 'admin_enqueue_scripts' action to load the styles only on the admin side
add_action('admin_enqueue_scripts', 'enqueue_custom_files');


// Track post views
function avc_track_views($post_id) {
    if (!is_singular() || is_preview() || current_user_can('manage_options')) return;

    if (empty($post_id)) {
        global $post;
        if (empty($post)) return; // Ensure global $post is available
        $post_id = $post->ID;
    }

    $count_key = 'avc_count';
    $count = get_post_meta($post_id, $count_key, true);

    if ($count == '') {
        $count = 0;
        delete_post_meta($post_id, $count_key);
        add_post_meta($post_id, $count_key, '0');
    } else {
        $count++;
        update_post_meta($post_id, $count_key, $count);
    }
}
add_action('wp_head', 'avc_track_views');




// Create admin page for views analytics
function avc_views_analytics_page() {
    add_menu_page(
        'View Analytics',
        'View Analytics',
        'manage_options',
        'avc-views-analytics',
        'avc_views_analytics_page_content',
        'dashicons-chart-line',
        20
    );
}
add_action('admin_menu', 'avc_views_analytics_page');

// Content for the admin page
function avc_views_analytics_page_content() {
    ?>
    <div class="wrap">
        <div class="tablenav top awesome-filters">      
            <h2>View Analytics</h2>
        </div>    
        <div class="tablenav top awesome-filters">                  
            <div class="alignleft actions">
                <form method="get" action="">
                <?php  
               
               $excluded_post_types = array('attachment', 'e-landing-page', 'elementor_library'); // Add any post types you want to exclude

               $post_types = get_post_types(array('public' => true), 'objects');
               
               // Remove excluded post types
               foreach ($excluded_post_types as $excluded_type) {
                   if (isset($post_types[$excluded_type])) {
                       unset($post_types[$excluded_type]);
                   }
               }
               
               // Now $post_types contains only the valid post types
               foreach ($post_types as $post_type) {
                   // Access post type properties
                   $post_type->name; // Post type slug
                   
               }
               
                 ?>
                <input type="hidden" name="page" value="avc-views-analytics" />
                
                    <select id="postTypeDropdown" name="post_type_dropdown">
                        <option value=""><?php echo esc_html__('Select Post Type', 'text-domain'); ?></option>
                        <?php foreach ($post_types as $post_type) : ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected(isset($_GET['post_type_dropdown']) ? sanitize_key($_GET['post_type_dropdown']) : 'post', $post_type->name); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" value="Submit" class="button" >
                </form>
            </div>
        </div>

    <?php

    $args = array(
        'post_type'      => isset($_GET['post_type_dropdown']) ? sanitize_key($_GET['post_type_dropdown']) : 'post',
        'posts_per_page' => 20,
        'meta_key'       => 'avc_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'post_status' => array( 'publish' )
    );

    
    $query = new WP_Query($args);
    $dataPoints = array();
    
    while ($query->have_posts()) {
        $query->the_post();
        $dataPoints[] = array(
            'y'    => intval(get_post_meta(get_the_ID(), 'avc_count', true)),
            'title_main' => esc_html(get_the_title())
        );
    }
    
    wp_reset_postdata(); // Reset the post data to avoid conflicts
    
    // Output the data points as JSON, ensuring proper escaping
    $dataPointsJSON = wp_json_encode($dataPoints, JSON_NUMERIC_CHECK | JSON_HEX_APOS);

    
    
    if(sizeof($dataPoints)>0)
     { ?>
    
    <script>
        window.onload = function () {
            var chart = new CanvasJS.Chart("chartContainer", {
                theme: "light2", // "light1", "light2", "dark1", "dark2"
                animationEnabled: true,
                title: {
                    text: ""
                },
                axisY: {
                    title: "Views",
                    includeZero: true,
                    prefix: "",
                    suffix: ""
                },
                data: [{
                    type: "bar",
                    toolTipContent: "<b>{title_main}</b>",
                    yValueFormatString: "#,##0",
                    indexLabel: "{y} View",
                    indexLabelPlacement: "inside",
                    indexLabelFontWeight: "bolder",
                    indexLabelFontColor: "white",
                    dataPoints: <?php echo $dataPointsJSON; ?>
                }]
            });
            chart.render();
        }
    </script>

    
    <div id="chartContainer"></div>
    <?php } else { echo '<strong class="ndf">No Data Found</strong>'; } ?>
   
    
    </div>
    <?php
}


