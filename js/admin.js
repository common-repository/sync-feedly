jQuery(document).ready(function($) {
    $("#start_feedly_sync").on("click", function(){
        $(".loader").css("visibility","visible");
        console.log("asdf");
        $.post(ajaxurl, {           //POST request
            action: "crsf_sync_run",               //action
            type: "one"
        }, function(data) {                         //callback
            // alert(data.string);
            if(data.success==true){
                alert("Run Feedly Sync Successfully!");
            }else{
                alert(data.string);
            }  
            $(".loader").css("visibility","hidden");     
        },'json');
    })

     
});
