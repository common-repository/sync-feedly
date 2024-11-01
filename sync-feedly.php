<?php
/*Plugin Name: Sync Feedly
/*
Plugin URI: http://vladitour.com/wordpress-feedly-sync-plugin/
Description: Simple interface to sync feedly
Version: 1.0.1
Author: Cristian Robert
Author URI: http://vladitour.com/
License: GPLv2 or later
License URI: http://www.opensource.org/licenses/gpl-license.php

Sync Feedly (Wordpress Plugin)
Copyright (C) 2019-2020 Cristian Robert
Contact me at http://vladitour.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

*/

require_once( plugin_dir_path( __FILE__ ) . 'admin/view.php' );
require_once( plugin_dir_path( __FILE__ ) . 'admin/setting.php' );

//cristian robert's sync feedly plugin install function
function crsf_activation_install() {

    //check if function is already defined before in other plugins
    // if(function_exists('crsf_menu_page_html') ||
    //     function_exists('crsf_settings_init') ||
    //     function_exists('crsf_cron_job_func') ||
    //     function_exists('crsf_menu_page_setting_html') ||
    //     function_exists('crsf_plugin_ajax') ){
    //         var_dump( get_defined_functions() );
    //     die('Plugin Not activated, there are already same name functions in pre-installed plugins');
    // }

    // clear the permalinks after the post type has been registered
    flush_rewrite_rules();

    //create feedly info table
    global $wpdb; 
    $crsf_table_name = $wpdb->prefix . 'crsf_board_detail';  // table name
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    //Check to see if the table exists already, if not, then create it
    if($wpdb->get_var( "show tables like '$crsf_table_name'" ) != $crsf_table_name ) 
    {
        $sql = "CREATE TABLE $crsf_table_name (
                    id int(11) NOT NULL auto_increment,
                    label varchar(60) NOT NULL,
                    created varchar(60) NOT NULL,
                    id_board varchar(300) NOT NULL,
                    description text NOT NULL,
                    id_category int(11) NOT NULL,
                    UNIQUE KEY id (id)
            ) $charset_collate;";
        dbDelta( $sql );
    }

    $crsf_table_name = $wpdb->prefix . 'crsf_synced_articles';  // table name
    if($wpdb->get_var( "show tables like '$crsf_table_name'" ) != $crsf_table_name ) 
    {
        $sql = "CREATE TABLE $crsf_table_name (
                    id int(11) NOT NULL auto_increment,
                    article varchar(300) NOT NULL,
                    UNIQUE KEY id (id)
            ) $charset_collate;";
        dbDelta( $sql );
    }

    $crsf_table_name = $wpdb->prefix . 'crsf_test';  // table name
    if($wpdb->get_var( "show tables like '$crsf_table_name'" ) != $crsf_table_name ) 
    {
        $sql = "CREATE TABLE $crsf_table_name (
                    id int(11) NOT NULL auto_increment,
                    label text NOT NULL,
                    time varchar(60) NOT NULL,
                    UNIQUE KEY id (id)
            ) $charset_collate;";
        dbDelta( $sql );
    }

}
register_activation_hook( __FILE__, 'crsf_activation_install' );


/* Deactivationn hook */
function crsf_deactivation_uninstall() {
    global $wpdb;
    //delete the table
    $crsf_table_name = $wpdb->prefix . 'crsf_board_detail';
    $crsf_table_name_1 = $wpdb->prefix . 'crsf_synced_articles';
    $crsf_table_name_2 = $wpdb->prefix . 'crsf_test';
    $sql = "DROP TABLE IF EXISTS $crsf_table_name;";
    $wpdb->query($sql);
    $sql = "DROP TABLE IF EXISTS $crsf_table_name_1";
    $wpdb->query($sql);
    $sql = "DROP TABLE IF EXISTS $crsf_table_name_2;";
    $wpdb->query($sql);
    
    // clear the permalinks to remove our post type's rules from the database
    flush_rewrite_rules();
    //clear cron job
    wp_clear_scheduled_hook( 'feedly_cron_job' );
}
register_deactivation_hook( __FILE__, 'crsf_deactivation_uninstall' );



//add admin menu hook action
add_action( 'admin_menu', 'crsf_manage_page' );
//admin menu add function
function crsf_manage_page() {
    add_menu_page(
        'Feedly Admin Manage',  //page title
        'Feedly Manage',        //menu title
        'manage_options',       //capability
        'crsf_manage_menu',   //menu slug
        'crsf_menu_page_html',  //cristian robert's sync feedly menu page callable function
        'dashicons-format-status',
        20
    );
    add_submenu_page(
        'crsf_manage_menu',    //parent slug
        'Feedly Settings',   //page title
        'Settings',             //menu title
        'manage_options',       //capability
        'crsf_manage_setting',//menu slug
        'crsf_menu_page_setting_html'//callable function
    );
}

//ini function
function crsf_plugin_init(){ 

    if( !session_id() )session_start();
        
    //register css file
    wp_register_style('crsf_style', plugins_url('style.css',__FILE__ ));
    wp_enqueue_style('crsf_style');

}

//add init function to action hook
add_action('init', 'crsf_plugin_init');


function crsf_cron_job_func(){

    global $wpdb;

    // $table_test = $wpdb->prefix.'crsf_test';

    // $result_test = $wpdb->insert($table_test, array(
    //         'label'     => 'ran',
    //         'time'  => date("Y-m-d h:i:s"))
    //     ); 
    // if ($result_test === false) {echo "SQL error:";}
    

    $log_info='';

    $crsf_table = $wpdb->prefix.'crsf_board_detail';
    $crsf_table1 = $wpdb->prefix.'crsf_synced_articles';

    require dirname(__FILE__)."/feedly-api/vendor/autoload.php";

    $feedly_options = get_option( 'feedly_options' );
    $clientSecret = $feedly_options['authkey'];
    if($feedly_options==false){
        $result['success']=false;
        $result['string']='Authkey is not set yet! First save Authenticate key with save button!';
        echo json_encode($result);
        wp_die(); // all ajax handlers should die when finished
    }
    $feedly = new feedly\Feedly(new feedly\Mode\DeveloperMode(), new feedly\AccessTokenStorage\AccessTokenSessionStorage());

    //set access token for feedly
    $feedly->storeDevToken($clientSecret);
    $boards = $feedly->boards();
    $stream = $feedly->streams();

    foreach($boards->fetch() as $board){//for each board item
        if($board['customizable']==1){

            $id_board=$board['id'];
            $id_category=1;
            $log_info.="     Handling board, id:".$id_board;

            //if it is a new board, then add to categories
            $result = $wpdb->get_results("SELECT * FROM $crsf_table WHERE id_board = '$id_board'");

            if ($wpdb->num_rows == 0) {
                //check if this category exists
                $term = term_exists( $board['label'], 'category' );
                if ( $term !== 0 && $term !== null ) {
                    $id_category=$term["term_id"];
                    $log_info.="     This category already exists in site.".$board['label'].":".$id_category;
                }else{
                    $category_result=wp_insert_term(
                        $board['label'], // the term 
                        'category', // the taxonomy
                        array(
                        'description'=> $board['description'],
                        'slug' => str_replace(" ", "-", strtolower($board['label']))
                        )
                    );
                    $id_category=$category_result["term_id"];
                    $log_info.="     Found new category and inserted to site:".$board['label'].":".$id_category;
                }

                $result = $wpdb->insert($crsf_table, array(
                                    'label'     => $board['label'],
                                    'created'   => $board['created'], 
                                    'id_board'  => $id_board,
                                    'description'=>$board['description'], 
                                    'id_category'=>$id_category)
                                ); 
                if ($result === false) {
                    $log_info.="     Error :inserting new board info to table;";
                }
                
            }else{
                foreach($result as $row_data){
                    $id_category=intval($row_data->id_category);
                }
            }
            
            //get all articles with board id, and check if already synced
            $articles=$stream->get($id_board,"contents");
            foreach($articles["items"] as $article){

                $result_find_article = $wpdb->get_results("SELECT * FROM $crsf_table1 WHERE article = '".$article['id']."'");

                //new article, have to add post
                if ($wpdb->num_rows == 0) {
                    $log_info.="     Found new article".$article['id'];
                    // Gather post data.
                    $article_content=(isset($article['content']['content']))?$article['content']['content']:$article['summary']['content'];
                    $my_post = array(
                        'post_title'    => $article['title'],
                        'post_content'  => $article_content,
                        'post_date'     => date( 'Y-m-d H:i:s', substr($article['published'], 0, 10) ),
                        'post_status'   => 'publish',
                        'post_author'   => 1,
                        'post_category' => explode(" ",$id_category)
                    );
                    
                    // Insert the post into the database.
                    $post_id=wp_insert_post( $my_post );
                    if ( ! is_wp_error( $post_id ) ){ 
                        //set tags for post
                        $post_tags = $article['keywords'];
                        $post_tags[] = $board['label'];
                        
                        wp_set_post_tags( $post_id, array_map('ucfirst',$post_tags) , true);

                        $article_engagement = isset($article['engagement'])?$article['engagement']:0;
                        $article_engagement_rate = isset($article['engagementRate'])?$article['engagementRate']:0;
                        $wpdb->insert($crsf_table1, array(
                            'article' => $article['id']
                        ));
                    
                        //add cms special post meta
                        add_post_meta( $post_id, 'cmsmasters_composer_show', 'false', true);
                        add_post_meta( $post_id, 'cmsmasters_gutenberg_show', 'false', true);
                        add_post_meta( $post_id, 'cmsmasters_composer_fullscreen', 'false', true);
                        add_post_meta( $post_id, 'slide_template', 'default', true);
                        add_post_meta( $post_id, 'cmsmasters_post_image_show', 'false', true);
                        add_post_meta( $post_id, 'cmsmasters_post_video_type', 'embedded', true);
                        add_post_meta( $post_id, 'cmsmasters_post_video_links', 'a:1:{i:0;s:0:"";}', true);
                        add_post_meta( $post_id, 'cmsmasters_post_audio_links', 'a:1:{i:0;s:0:"";}', true);
                        add_post_meta( $post_id, 'cmsmasters_post_title', 'true', true);
                        add_post_meta( $post_id, 'cmsmasters_post_sharing_box', 'false', true);
                        add_post_meta( $post_id, 'cmsmasters_post_author_box', 'true', true);
                        add_post_meta( $post_id, 'cmsmasters_post_more_posts', 'related', true);
                        add_post_meta( $post_id, 'cmsmasters_post_read_more', 'Read More', true);
                        add_post_meta( $post_id, 'cmsmasters_page_scheme', 'default', true);
                        add_post_meta( $post_id, 'cmsmasters_layout', 'r_sidebar', true);
                        add_post_meta( $post_id, 'cmsmasters_bottom_sidebar', 'true', true);
                        add_post_meta( $post_id, 'cmsmasters_bottom_sidebar_layout', '14141414', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_default', 'true', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_col', '#f4f4f4', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_img_enable', 'false', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_rep', 'no-repeat', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_pos', 'top center', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_att', 'scroll', true);
                        add_post_meta( $post_id, 'cmsmasters_bg_size', 'cover', true);
                        add_post_meta( $post_id, 'cmsmasters_heading', 'disabled', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_alignment', 'left', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_scheme', 'default', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_bg_img_enable', 'false', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_bg_rep', 'no-repeat', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_bg_att', 'scroll', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_bg_size', 'cover', true);
                        add_post_meta( $post_id, 'cmsmasters_heading_height', '102', true);
                        add_post_meta( $post_id, 'cmsmasters_breadcrumbs', 'true', true);
                        add_post_meta( $post_id, 'cmsmasters_likes', $article_engagement, true);
                        add_post_meta( $post_id, 'cmsmasters_rate_like', $article_engagement_rate, true);


                        /****************************** set featured img for post *********************** */
                        $image_url        = $article['visual']['url'];      // Define the image URL here
                        $image_name       = str_replace(" ", "_", strtolower($article['title'])).".png";
                        $upload_dir       = wp_upload_dir();                // Set upload folder
                        
                        $request = wp_remote_get($image_url);//get featured image of posts from url
                        $image_data = wp_remote_retrieve_body( $request );

                        $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
                        $filename         = basename( $unique_file_name );  // Create image file name

                        // Check folder permission and define file location
                        if( wp_mkdir_p( $upload_dir['path'] ) ) {
                            $file = $upload_dir['path'] . '/' . $filename;
                        } else {
                            $file = $upload_dir['basedir'] . '/' . $filename;
                        }

                        // Create the image  file on the server
                        file_put_contents( $file, $image_data );

                        // Check image file type
                        $wp_filetype = wp_check_filetype( $filename, null );

                        // Set attachment data
                        $attachment = array(
                            'post_mime_type' => $wp_filetype['type'],
                            'post_title'     => sanitize_file_name( $filename ),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );

                        // Create the attachment
                        $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

                        // Include image.php
                        require_once(ABSPATH . 'wp-admin/includes/image.php');

                        // Define attachment metadata
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

                        // Assign metadata to attachment
                        wp_update_attachment_metadata( $attach_id, $attach_data );

                        // And finally assign featured image to post
                        set_post_thumbnail( $post_id, $attach_id );

                    }else{
                        $log_info.="     Error in add post";
                    }

                }

                    
            }

        }
            
    }

    $crsf_table_test = $wpdb->prefix.'crsf_test';

    $result_test = $wpdb->insert($crsf_table_test, array(
            'label'     => $log_info,
            'time'  => date("Y-m-d h:i:s"))
        ); 
    if ($result_test === false) {echo "SQL error:";}

    if($_POST['type']=='one'){
        $result['success']=true;
        $result['string']='Run sync successfully!';
        echo json_encode($result);
        wp_die(); // all ajax handlers should die when finished
    }
}

// ------------------------Ajax function declare---------------------------------------------
add_action('wp_enqueue_scripts', 'crsf_plugin_ajax');

function crsf_plugin_ajax() {
    //need create the nonce to check if this is valid ajax request, but not bad request
    $title_nonce = wp_create_nonce('crsf_feedly_nonce');

    wp_localize_script('ajax-feedly-script', 'feedly_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => $title_nonce,
    ));
}

add_action( 'admin_enqueue_scripts', 'crsf_admin_init' );
function crsf_admin_init() {
    wp_enqueue_script('crsf_admin_js', plugins_url('js/admin.js',__FILE__ ));
}
 
//create new project ajax action
add_action('wp_ajax_crsf_sync_run', 'crsf_cron_job_func');

/**
* register our crsf_settings_init to the admin_init action hook
*/
add_action( 'admin_init', 'crsf_settings_init' );

function crsf_plugin_action_links( $links ) {
    $links = array_merge( 
        array('<a href="' . esc_url( admin_url( '/admin.php?page=crsf_manage_setting' ) ) . '">' . __( 'Settings', 'crsf_wpplugin' ) . '</a>'), 
        $links, 
        array('<a href="http://vladitour.com/wordpress-feedly-sync-plugin/">' . __( 'Go Pro', 'crsf_wpplugin' ) . '</a>') 
    );
    return $links;
}
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'crsf_plugin_action_links' );

?>
