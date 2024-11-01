<?php
function crsf_menu_page_html(){
    global $wp;
?>
    <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <h3>Board Info</h3>
    <table class="widefat fixed" cellspacing="0">
        <thead>
        <tr>
        <!-- // this column contains checkboxes -->
                <th id="cb" class="manage-column column-no" scope="col">No</th> 
                <th id="columnname" class="manage-column column-label" scope="col">Label</th>
                <th id="columnname" class="manage-column column-id" scope="col">Id</th>
                <!-- // "num" added because the column contains numbers -->
                <th id="columnname" class="manage-column column-created" scope="col">Created at</th> 

        </tr>
        </thead>

        <tfoot>
            <tr>
                    <!-- <th class="manage-column column-cb check-column" scope="col">as</th>
                    <th class="manage-column column-columnname" scope="col">asdf</th>
                    <th class="manage-column column-columnname num" scope="col">qwer</th> -->

            </tr>
        </tfoot>

        <tbody>
            <?php
            global $wpdb;
            $crsf_table = $wpdb->prefix.'crsf_board_detail';
            $count=0;
            $mylink = $wpdb->get_results( "SELECT * FROM $crsf_table WHERE 1");
        
            foreach($mylink as $row_data){
                $timestamp = substr($row_data->created, 0,10);//change from epoch timestamp to normal timestamp
                echo '<tr class="alternate">
                        <td class="column-no">'.(++$count).'</td>
                        <td class="column-label">'.$row_data->label.'</td>
                        <td class="column-id">'.$row_data->id_board.'</td>
                        <td class="column-created">'.(date('Y-m-d h:i:s',$timestamp)).'</td>
                    </tr>';
            }
            ?>
        </tbody>
    </table>
    </div>
<?php


}
?>
