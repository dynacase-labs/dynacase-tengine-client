$(document).ready(function () {
    
    var url = $("#sendConversion").attr('action');
    var engines = null;

    var cleanIHM = function(init) {
	if (init) {
	    $("#sendConversion button").button().hide();
	    $("#showmimeengine").hide();
	}
	$( "input[type=submit], input[type=file]" ).button();
	$("#reqErrorMessage").hide();
	$("#uploadResult button").button().hide();
	$(".panel.result").hide();
	$(".panel.error").hide();
	$("#processinfo").empty();
	$(".mimeslist").hide();
    };
    
    if (!window.FormData) {
	alert('Upgrade your browser !'); 
	return;
    }

    cleanIHM(true);

    $('#showmimeengine')
	.on('click', function() {
	    $("#mimeslist").empty();
	    var currentEngine = $("#engine option:selected").val()
	    if (engines[currentEngine] !== undefined) {
		$("#curengine").html(currentEngine);
		for (var im=0; im<engines[currentEngine].mimes.length; im++) {
		    $("#mimeslist").append($("<div />").html(engines[currentEngine].mimes[im]));
		}
	    }
	    $(".mimeslist").show();
	});
    $('#engine')
	.on('change', function() {
	    $(".mimeslist").hide();
	});


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

    $('#thefile').prop('disabled', true);
    $('#engine').prop('disabled', true);
    serverVersion.check( function( sr ) {
	if (sr.status == 0) {
	    globalMessage.show("[TEXT:TE:Client:not fully supported server version, need server version ]"+" "+sr.required+".", 'warning');
	} else if (sr.status == -1) {
	    globalMessage.show("[TEXT:tengine_client_selftests:server communication error]"+"<br/>"+sr.message+".", 'error');
	} else {
	    $('#thefile').prop('disabled', false);
	    $('#engine').prop('disabled', false);
	    $.ajax({
		url: url+"&op=engines",
		type: "GET",
		success: function(data) {
		    if (!data.success) {
			$(".panel.error").html("[TEXT:tengine_client_selftests:server communication error]<br/>"+data.message).css('display', 'block');
		    } else {
			engines = data.info;
			console.log(engines);
			for (var key in data.info) {
			    if (data.info.hasOwnProperty(key)) {
				$("#engine").append("<option value='"+key+"'>"+key+"</option>");
			    }
			}
			$("#engine").css('display', 'inline');
			$("#showmimeengine").show();
		    }
		},
		error:  function() {
		    $(".panel.error").html('Oooops, something wrong happens').css('display', 'block');
		}
	    });
	    
	}
    });



});
