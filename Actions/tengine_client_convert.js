$(document).ready(function () {
    
    var url = $("#sendConversion").attr('action');
    
    var cleanIHM = function(init) {
	if (init) {
	    $("#sendConversion button").button().hide();
	}
	$( "input[type=submit], input[type=file], a" ).button();
	$("#reqErrorMessage").hide();
	$("#uploadResult button").button().hide();
	$(".panel.result").hide();
	$(".panel.error").hide();
	$("#processinfo").empty();
    };
    
    if (!window.FormData) {
	alert('Upgrade your browser !'); 
	return;
    }

    cleanIHM(true);

    $('#thefile').on('change', function() {
	$("#sendConversion button").button().show();
    });

    var logInfos = function(infos) { 
	var infos = infos;
	var now = new Date();
	var hh = now.getHours();
	hh = ( hh < 10 ? "0"+hh : hh );
	var mm = now.getMinutes();
	mm = ( mm < 10 ? "0"+mm : mm );
	var ss = now.getSeconds();
	ss = ( ss < 10 ? "0"+ss : ss );
	var dateStr = '[' + + ']';
	$('#processinfo').append(
	    $('<div /> ').addClass('task info').append(function() {
		var htmlContent = '<span class="date">'+hh+':'+mm+':'+ss+'</span>';
		htmlContent  += '<span class="status status_'+infos.status+'">'+infos.status+'</span>';
		[ 'engine', 'inmime' , 'comment' ].forEach( function( index ) {
		    htmlContent += '<span class="value '+index+'">'+infos[index]+'</span>';
		});
		return htmlContent;
	    })
	);
    };
    
    var updateStatus = function( tid ) {
	var formData = new FormData();
	formData.append("tid", tid);
	formData.append("op", "info");
	$.ajax({
            url: url,
            type: "POST",
            data: formData,
            processData: false,
	    contentType: false,
            success: function(data) {
		logInfos(data.info);
		if (data.info.status == 'D') {
		    $("#uploadResult button").button().show();
		    $("#uploadResult").on('submit', function() {
			$(this).attr('action', $(this).attr('action') + '&tid=' + tid);
			$("#uploadResult button").button().hide();
		    });
		} else if (data.info.status == 'W' || data.info.status == 'P' ) {
		    setTimeout( function() {
			updateStatus(data.info.tid);
		    }, 1000);
		} 
	    },
            error: function() { alert('error'); }
        });
    };

    $("#sendConversion").on("submit", function(event) {

	cleanIHM(false);
	var formData = new FormData(document.getElementById('sendConversion'));
	formData.append("op", "convert");
        $.ajax({
            url: url,
            type: "POST",
            data: formData,
            processData: false,
	    contentType: false,
            success: function(data) {
		if (!data.success) {
		    $("#reqErrorMessage").html(data.message);
		    $(".panel.error").show();
		} else {
		    $(".panel.result").show();
		    $('#tid').html(data.info.tid);
		    logInfos(data.info);
		    updateStatus(data.info.tid);
		}
	    },
            error: function() { alert('error'); }
        });
	event.preventDefault();
    });

    $.ajax({
	url: url+"&op=engines",
	type: "GET",
	success: function(data) {
	    if (!data.success) {
		$(".panel.error").html("[TEXT:tengine_client_selftests:server communication error]<br/>"+data.message).css('display', 'block');
	    } else {
		for (var io=0; io<data.info.length; io++) {
		    $("#engine").append("<option value='"+data.info[io].name+"'>"+data.info[io].name+' ('+data.info[io].mime+")</option>");
		    
		}
		$("#engine").css('display', 'inline');
	    }
	},
	error:  function() {
	    $(".panel.error").html('Oooops, something wrong happens').css('display', 'block');
	}
    });
    


});
