$(document).ready(function () {
// Handler for .ready() called.


    $('input').button();
    var buttons = "";
    $("select").each(function () {
        var rb = '', idrb = '';
        for (var i = 0; i < this.options.length; i++) {
            idrb = this.id + '_' + i;
            rb = $('<input type="radio" value="'+this.options[i].value+'" id="' + idrb + '" ' + ((this.options[i].selected) ? "checked" : "") + '  name="' + this.name + '" /><label for="' + idrb + '">' + this.options[i].label + '</label>');
            $(this).parent().append(rb);
        }

        rb=$('<input type="hidden" data-original="1" id="'+this.id+'"/>');
        $(this).parent().append(rb);
        $(this).remove();
    });

    $('input[type=radio]').parent().buttonset();



    $("body").on("MODPARAMETER", function (event, data) {
        if (data.success) {
            var pid = data.parameterid;
            if (data.data.modify) {
                $('#' + data.data.parameterid +',#'+data.data.parameterid+'_0').parents('.editapplicationparameter').find('div[data-type=label] label').addClass('modified');

            }
        } else {
            alert(data.error);
        }
    });

    $("body").on("mouseup", "label.ui-button",function (event) {
        if (! $(this).hasClass('ui-state-active')) {
        //$(this).trigger("change");
            var originValue=$('#'+$(this).attr('for')).val();
            $(this).parents('form').find('input[data-original=1]').val(originValue);
            sendParameterApplicationData(this);
        }
    } );


    $("form").on("keypress", "input[type=text]",function (e) {
        var code = e.keyCode || e.which;
        if (code == 13) {
            e.preventDefault();
            sendParameterApplicationData(this);
            return false;
        }

    } );
});