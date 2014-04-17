var globalMessage = {
    fadeInDuration: 5000,
    fadeOutDuration: 5000,
    hide: function() {
	$('#global-message').fadeOut({ 
	    duration: this.duration,
	    complete: function() {
		$('#global-message .message-content').remove();
	    }
	});
    },
    show: function(msg, level) {
	var gm = this;
	var levelCss = 'level-info';
	switch(level) {
	case 'warning' :
	case 'error' : 
	    levelCss = 'level-'+level;
	    levelCss = 'level-'+level;
	    break;
	};
	if ($('#global-message')[0] == undefined) {
	    $('body')
		.prepend(
		    $('<div/>', { id:'global-message'})
			.addClass('message-box')
			.css('display', 'none')
			.on('click', function () {
			    gm.hide();
			})
			.append(
			    $('<a/>')
				.html('×')
				.attr('title','[TEXT:TE:Client:close message box]')
				.addClass('close-message-box')
				.on('click', function () {
				    gm.hide();
				})
			)
		);
	}
	
	$('#global-message')
	    .append(
		$('<div/>')
		    .html(msg)
		    .addClass('message-content')
		    .addClass(levelCss)
	    )
	    .fadeIn( { duration: 500 });
	return this;
    }
};

var serverVersion = {
    required: "1.4.0",
    current: null,
    compat: false,
    parseVersionFloat: function(versionString) {
	var versionArray = ("" + versionString)
            .replace("_", ".")
            .replace(/[^0-9.]/g, "")
            .split("."),
	sum = 0;
	for (var i = 0; i < versionArray.length; ++i) {
            sum += Number(versionArray[i]) / Math.pow(10, i * 3);
	}
	return parseFloat(sum);
    },
    check: function(handler) {
	var sversion = null;
	if (this.compat) return { status:true, required:this.required, message:'' };
	if (this.current != null) {
	    sversion = this.current;
	} else {
	    var gm = this;
	    var response = $.ajax({
		type: "GET",
		url: "?app=TENGINE_CLIENT&action=TENGINE_CLIENT_INFOS",
		dataType: "json",
		success: function(data) {
		    if (data.success) {
			gm.current = data.info.version;
			var vreq = gm.parseVersionFloat(gm.required);
			var vcur = gm.parseVersionFloat(gm.current);
			if (vreq <= vcur) {
			    gm.compat = true;
			    handler({ 
				status:1, 
				required:gm.required, 
				message:'' 
			    });
			} else {
			    handler({ 
				status: 0, 
				required:gm.required, 
				message:'[TEXT:TE:Client:unsupported server version]'
			    });
			}
		    } else {
			handler( { 
			    status: -1, 
			    required:gm.required, 
			    message:data.message
			});
		    }			
		},
		error: function() {
		    handler( { 
			status: -1, 
			required:gm.required, 
			message:response.status+' / '+response.responseText
		    });
		}
	    });
	}
    }
}


