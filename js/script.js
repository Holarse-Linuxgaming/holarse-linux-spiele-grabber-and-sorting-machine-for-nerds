$(function()
{
    $('table').tablesorter(
    {
        showProcessing : true,
        widgets        : ['columns'],
        usNumberFormat : false,
        sortReset      : true,
        sortRestart    : true
    });

    var delay = (function()
    {
        var timer = 0;
        return function(callback, ms)
        {
            clearTimeout (timer);
            timer = setTimeout(callback, ms);
        };
    })();

    var duplicateFilter=(function()
    {
        var lastContent;
        return function(content,callback)
        {
            content=$.trim(content);
            if(content!=lastContent)
            {
                callback(content);
            }
            lastContent=content;
        };
    })();

    $('.ribbon').each(function()
    {
        var rtitle = $(this).attr('data-hint');
        $(this).attr('title',rtitle);
    });

    $('#search_input,#search_select').on('keyup change',function(ev)
    {
        var self=this;
        delay(function()
        {
            duplicateFilter($(self).val(),function(c)
            {
                var a=$('#search_input').val().trim();
                var b=$('#search_select option:selected').text();
                if (a.length >= 2 && b.length > 0 || a.length == 0 && b.length > 0)
                {
                    $('#lstable tbody tr').css('opacity','.5');
                    $.ajax({
                        type:"POST",
                        dataType:"html",
                        url:"./ajax.php",
                        data:"ajax=suche&suche="+a+"&in="+b,
                        success:function(html)
                        {
                            $('#lstable tbody').html(html);
                        }
                    });
                }
            });
        }, 400 );
        return false;
    });
});