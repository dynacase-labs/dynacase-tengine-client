$(document).ready(function () {

    var url = $("#sendConversion").attr('action');

    var onTESuccess = function(idx) {
	return function(data) {
	    if (data.success) {
		if (!data.info.status) {
		    $("#"+idx).removeClass('in-progress').addClass('statusKO');
		} else {
		    $("#"+idx).removeClass('in-progress').addClass('statusOK');
		}
		if (data.info.output != undefined && data.info.output.length > 0) {
		    $("#"+idx+" .log")
			.append(function() {
			    var html = '<div />';
			    for (var i=0; i<data.info.output.length; i++) {
				html += '<div>'+data.info.output[i]+'</div>';
			    }
			    html += '</div>';
			    return html;
			});
		}
	    } else {
		$("<div />").html(data.message).appendTo("#"+idx+" .log");
		$("#"+idx).removeClass('in-progress').addClass('statusKO');
	    }
	}
    };

    var onTEError = function(idx) { 
	return function() {
	    $("#"+idx).removeClass('in-progress').addClass('statusKO');
	    $("#"+idx+" .log").html('Oooops, something wrong happens');
	}
    };

    $("#sendconversion").button({ disabled: true });

    $("#testid").on("change", function() {
	if ($( "#testid option:selected").val() != "") {
	    $("#sendconversion").button("enable");
	    $("#testid option[value='']").remove();
	} else {
	    $("#sendconversion").button("disable");
	}
    });

    $("#sendConversion").on("submit", function(event) {
	$("#results").empty();
	var formData = new FormData(document.getElementById("sendConversion"));
	var idConv = $("#testid option:selected").val();
	if (idConv=="") return false;
	var ConversionList = new Array();
	if (idConv=="*") {
	    $("#testid option").each(function() {
		if ($(this).val()!="*" &&  $(this).val()!="" ) ConversionList[ConversionList.length] = { id: $(this).val(), label: $(this).text() };
	    });
	} else {
	    ConversionList[0] = { id: idConv, label: $("#testid option:selected").text() };
	}

	for (var idxconv=0; idxconv < ConversionList.length; idxconv++ ) {
	    formData.append('selftestid', ConversionList[idxconv].id);
	    var convid = 'R-'+ConversionList[idxconv].id+'-'+Date.now();
	    var newResult = $(".tmpl_result").clone()
		.attr('id', convid)
		.removeClass('template tmpl_result')
		.appendTo("#results");
	    $("#"+convid+" .title").html(ConversionList[idxconv].label);
            $.ajax({
		url: url,
		type: "POST",
		data: formData,
		processData: false,
		contentType: false,
		success: onTESuccess(convid),
		error:  onTEError(convid)
            });
	}
	event.preventDefault();
    });

    $.ajax({
	url: url+"&op=engines",
	type: "GET",
	success: function(data) {
	    if (!data.success) {
		$(".panel.error").html("[TEXT:tengine_client_selftests:server communication error]<br/>"+data.message).css('display', 'block');
	    } else {
		for (var key in data.info) {
		    if (data.info.hasOwnProperty(key)) {
			 $("#testid").append("<option value='"+key+"'>"+data.info[key].description+"</option>");
		    }
		}
		$("#sendConversion").css("display", "inline");
	    }
	},
	error:  function() {
	    $(".panel.error").html('Oooops, something wrong happens').css('display', 'block');
	}
    });
    
    
});
