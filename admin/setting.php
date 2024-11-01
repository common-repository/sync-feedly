<?php

function crsf_settings_init() {

    register_setting( 
        'wporg',        
        'feedly_options'
    );
    
    // register a new section in the "wporg" page
    add_settings_section(
        'feedly_typeservice_section',         //Slug-name to identify the section
        __( 'Value Of Management', 'crsf_wpplugin' ),      //Formatted title of the section
        'crsf_setting_sections_func',          //Function that echos out any content at the top of the section (between heading and fields).
        'wporg'
    );
    
    // register a new field in the "feedly_typeservice_section" section
    add_settings_field(
        'feedly_auth_key', //Slug-name to identify the field. 
        __( 'Feedly Authenticate Key', 'crsf_wpplugin' ),//Formatted title of the field. Shown as the label for the field during output.
        'crsf_setting_fields_func',//Function that fills the field with the desired form inputs. The function should echo its output.
        'wporg',//slug-name of the settings page on which to show the section 
        'feedly_typeservice_section',//slug-name of the section in which to show the box.
        [                               //Extra arguments used when outputting the field.
            'label_for' => 'wporg_field_pill',
            'class' => 'wporg_row',
            'wporg_custom_data' => 'custom',
        ]
    );
    
    add_settings_field(
        'run_sync_time', //Slug-name to identify the field. 
        __( 'Run Sync', 'crsf_wpplugin' ),//Formatted title of the field. Shown as the label for the field during output.
        'crsf_run_sync_time_func',//Function that fills the field with the desired form inputs. The function should echo its output.
        'wporg',//slug-name of the settings page on which to show the section 
        'feedly_typeservice_section'//slug-name of the section in which to show the box.
    );

}


function crsf_setting_sections_func( $args ) {
    ?>
    <p id="<?php echo esc_attr( $args['id'] ); ?>">
        <?php esc_html_e( 'Value for management.', 'wporg'); ?>
    </p>
    <?php
}
    

function crsf_setting_fields_func( $args ) {
    // get the value of the setting we've registered with register_setting()
    // $options = get_option( 'wporg_options' );
    $feedly_options = get_option( 'feedly_options' );
    
    echo '<div class="feedly_option_container">
            <label calss="" for="type_authkey_input" >Authenticate key</label>
            <textarea rows="4" id="type_authkey_input" class="type_authkey_input" name="feedly_options[authkey]" >'.(isset($feedly_options['authkey'])?$feedly_options['authkey']:"").'</textarea>
        </div>';
    echo '<p class="description">';
    esc_html_e( 'Enter the authenticate key of feedly user! i.e: A3Dodu.... .... ....s11KIF2NgQH:feedlydev', 'wporg' ); 
    // print_r($feedly_options);
    echo '</p></div>';
}

function crsf_run_sync_time_func(){
    $feedly_options = get_option( 'feedly_options' );

    echo '<div class="one_row_div" style="vertical-align: top; margin-left: 10%">
            <button id="start_feedly_sync" type="button" class="button button-primary" style="vertical-align: middle">Sync Now One Time</button>
            <div class="loader"></div>
        </div>';

 
}


function crsf_menu_page_setting_html(){
    $feedly_options = get_option( 'feedly_options');
    // check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // add error/update messages
    
    // check if the user have submitted the settings
    // wordpress will add the "settings-updated" $_GET parameter to the url
    if ( isset( $_GET['settings-updated'] ) ) {
        
        
            global $wpdb;
            $crsf_table_test = $wpdb->prefix.'crsf_test';

            $result_test = $wpdb->insert($crsf_table_test, array(
                    'label'     => 'Updated settings; authkey='.$feedly_options['authkey'],
                    'time'  => date("Y-m-d h:i:s"))
            ); 
            if ($result_test === false) {echo "SQL error:";}
        
        // add settings saved message with the class of "updated"
        add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'crsf_wpplugin' ), 'updated' );
    }
    
    // show error/update messages
    settings_errors( 'wporg_messages' );
    ?>

    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
                // output security fields for the registered setting "wporg"
                settings_fields( 'wporg' );
                // output setting sections and their fields
                // (sections are registered for "wporg", each field is registered to a specific section)
                do_settings_sections( 'wporg' );
                // output save settings button
                submit_button( 'Save Settings' );
            ?>
        </form>
    </div>

    <?php
   }