jQuery(function($){
    $('a#clear_cache').bind('click',function(){
        var $this = $(this);
        if (!$this.data('oldhtml')){
            $this.data('oldhtml',$this.html());
        }
        if($this.html()=='Clearing...')return false;
        $this.html('Clearing...');
        var $type = $this.data('type');
        $.post(ajaxurl,{'tr_action':'clear_cache','action':'clear_cache',type:$type},function($rs){
            $this.html($this.data('oldhtml'));
            if ($type=='html'){
                $('.count_cache').html('0');
            }
            else if ($type=='js'){
                $('.count_cache_js').html('0');
            }else if ($type=='css'){
                $('.count_cache_css').html('0');
            }

        });
         return false;      
    });
});