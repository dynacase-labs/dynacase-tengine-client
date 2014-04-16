var globalMessage = {
    duration: 1000,
    timeout: null,
    hide: function() {
	console.log('hide',this);
	if (this.timeout!=null) clearTimeout(this.timeout);
	$('#global-message').fadeOut({
	    duration: 300,
	    complete: function() {
		$('#global-message').empty();
	    }
	});
    },
    show: function(msg, level) {
	this.display(msg, level, false);
    },
    display: function(msg, level, autoHide) {
	var levelCss = 'info';
	switch(level) {
	case 'warning' :
	case 'error' : 
	    levelCss = level;
	    break;
	};
	if (this.timeout!=null) clearTimeout(this.timeout);
	if ($('#global-message')[0] == undefined) {
	    $('body').prepend($('<div/>', { id:'global-message'}).addClass('message').css('display', 'none'));
	}
	var gm = this;
	$('#global-message')
	    .append($('<div/>').html(msg).addClass(levelCss))
	    .fadeIn( {
		duration: 500,
    		complete: function() {
		    console.log('show',gm);
		    if (autoHide) gm.timeout = setTimeout(globalMessage.hide, gm.duration);
		}
    	    });
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


