jQuery(function ($) {
    $('body').on('submit','form#add_new_kid_form',function () {
        var $f = $(this);
        var $btn = $('[type="submit"]',$f);
        if ($btn.val()=='Sending...')return false;
        $btn.val('Sending...');
        $('.alert', $f).html('');
        $.ajax({
            data:$f.serialize()+'&trajax=1',
            type:'post',
            dataType:'json',
            success:function (json) {
                $btn.val('Submit');
                $('.alert', $f).html(json.alert);
                if (json.status=='ok'){
                    location.href = json.redirect;
                }
            },
            error:function () {
                $btn.val('Submit');
            }
        })
        return false;
    })
})

