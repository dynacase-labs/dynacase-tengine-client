$(document).ready(function () {

    var serverApiOk = false;

    var countersUpdateInterval = 5000;
    var url = "?app=TENGINE_CLIENT&action=TENGINE_CLIENT_TASKS";

    var logtask = [];

   var displayLogTask = function(tid, target) {
	for (var il=0 ; il<logtask[tid].length ; il++ ) {
	    $('<div />').append($('<span />').addClass('date').html(logtask[tid][il].date))
		.append($('<span />').html(logtask[tid][il].comment))
		.appendTo(target);
	}
    };

    var histoHandler = function(tid, target) {
	return function(data) {
	    if (data == undefined) {
		$('<div />').html("[TEXT:tengine_client_selftests:server communication error]" +"<br/>"+'Oooops strange case, something happens...').addClass('alert').appendTo(target);
	    } else if (data.success) {
		logtask[tid] = data.data;
		displayLogTask(tid, target);
	    } else {
		$('<div />').html("[TEXT:tengine_client_selftests:server communication error]" +"<br/>"+data.message).addClass('alert').appendTo(target);
	    }
	};
    };

    var actionError = function(actions, msg) {
	actions.append( 
	    $('<div />').addClass('error').html(msg)
	);
    };


    function fnFormatDetails ( oTable, nTr ) {
	var aData = oTable.fnGetData( nTr );
	var sOut = $('<div />');

	var actions = $('<div />').addClass('actions').appendTo(sOut);
	if (aData.status == 'B' || aData.status == 'W' || aData.status == 'P' || aData.status == 'D' ) {
	    actions.append( 
		$('<a />')
		    .addClass('paginate_button')
		    .attr('title', "[TEXT:TE:Client:stop task processing and clean working datas]")
		    .html("[TEXT:TE:Client:Abort]")
		    .on('click', function(event) {
			event.stopPropagation();
			$.ajax({
			    url: url+'&op=abort&tid='+aData.tid,
			    type: "GET",
			    success: function(data) {
				if (data.success) {
				    globalMessage.show('[TEXT:task successfully aborted] ('+aData.tid+')', 'info');
				    logbook.fnDraw();
				} else {
				    globalMessage.show("[TEXT:task aborting fails]"+'<br/>'+data.message, 'warning');
				}
			    },
			    error:  function() {
				globalMessage.show("[TEXT:abort command execution fails]"+'<br/>'+data.message, 'error');
			    }
			});
		    })
	    );
	};
	
	var target = $('<div />').attr('name', "H"+aData.tid).addClass('log')
	    .append($('<div />').addClass('title').html('[TEXT:TE:Client:Log]'))
	    .appendTo(sOut);

	var dData = $('<div />').addClass('data')
	    .append($('<div />').addClass('title').html('[TEXT:TE:Client:Datas]'))
	    .appendTo(sOut);
	var il=0;
	for (var key in aData) {
	    if (aData.hasOwnProperty(key) && key!="0") {
		var spClass = 'left-float';
		if (il % 2 == 0) {
		    var lroot = $('<div />').appendTo(dData);
		} else {
		    spClass = 'right-float';
		}
		lroot.append(
		    $('<div />').addClass(spClass)
			.append($('<span />').addClass('key').html(key))
			.append($('<span />').addClass('value').html(aData[key]))
		    );
		il++;
	    }
	}
	if (logtask[aData.tid] == undefined) {
	    // Start history loading
            $.ajax({
		url: url+'&op=histo&tid='+aData.tid,
		type: "GET",
		success: histoHandler(aData.tid, target),
		error:  histoHandler(aData.tid, target)
            });
	} else {
	    displayLogTask(aData.tid, target);
	}
 	return sOut;
   }

    var loadDatatable = function() {

	return $('#tasks-dt').dataTable({
	    sDom: '<"top"i<"buttons">p>rt<"bottom"lp><"clear">',
	    oLanguage: {
		sLengthMenu: "[TEXT:TE:Client:Display _MENU_ tasks per page]",
		sZeroRecords: "[TEXT:TE:Client:Nothing found - sorry]",
		sInfo: "[TEXT:TE:Client:Showing _START_ to _END_ of _TOTAL_ records]",
		sInfoEmpty: "[TEXT:TE:Client:Showing 0 to 0 of 0 records]",
		sInfoFiltered: "[TEXT:TE:Client:(filtered from _MAX_ total records)]",
		sLoadingRecords: "[TEXT:TE:Client:sLoadingRecords]",
		sProcessing: "[TEXT:TE:Client:sProcessing]",
		sSearch: "[TEXT:TE:Client:sSearch]",
		sZeroRecords: "[TEXT:TE:Client:sZeroRecords]",
		oPaginate: {
		    sFirst: "[TEXT:TE:Client:sFirst]",
		    sLast: "[TEXT:TE:Client:sLast]",
		    sNext: "[TEXT:TE:Client:sNext]",
		    sPrevious: "[TEXT:TE:Client:sPrevious]"
		}
            },	
	    iDisplayLength: 25,
	    aLengthMenu: [[25, 50, 100], [25, 50, 100]],
	    sPaginationType: "full_numbers",
	    bServerSide: (function() { return serverApiOk })(), // true
	    bFilter: true,
	    bProcessing: true,
	    sAjaxSource: (function() { return (serverApiOk ? url+'&op=tasks' : null) })(),
	    aaData: (function() { return (serverApiOk ? null : [ ] ) })(),
	    bDestroy: (function() { return serverApiOk })(),
            fnServerData: function ( sSource, aoData, fnCallback ) {
		$.ajax( {
                    dataType: 'json',
                    type: "POST",
                    url: sSource,
                    data: aoData,
                    success: function (data, textStatus, jqXHR) {
			logbook.fnSettings().oLanguage.sEmptyTable = "[TEXT:tengine_client_selftests:server communication error]" +"<br/>"+data.message;
			fnCallback(data, textStatus, jqXHR);
                    }
		} );
            },
            aaSorting: [
		[0, 'desc']
	    ],
	    aoColumnDefs: [
		{ 
		    aTargets: [0],
		    mDataProp: "cdate", 
		    sClass: "date" 
		},
		{ 
		    aTargets: [1],
		    mDataProp: "statuslabel",
		    /* sTitle: "[TEXT:TE:Client:Status]",  */
		    fnRender: function(o) {
			var rhtml = '<span '
			    +       '   class="state flag flag_'+o.aData.status+'" '
			    +       '   title="'+o.aData.statuslabel+'"' 
			    +       '   >'
			    +       o.aData.statuslabel
			    +       '</span>';
			return(rhtml);
		    }
		},
		{ 
		    aTargets: [2],
		    mDataProp: "owner",
		    bSortable: false
		},
		{ 
		    aTargets: [4],
		    mDataProp: "doctitle",
		    bSortable: false, 
		    fnRender: function(o) {
			if (o.aData.doctitle=='') return '';
			var rhtml = '['+o.aData.docid+'] '+o.aData.doctitle;
			return(rhtml);
		    }
		},
		{ 
		    aTargets: [3],
		    mDataProp: "filename",
		    bSortable: false
		},
		{
		    aTargets: [5],
		    mDataProp: "engine"
		},
		{ 
		    aTargets: [6],
		    mDataProp: "tid",
		    bUseRendered: false,
		    fnRender: function(o) {
			if (o.aData.tid == undefined || o.aData.tid == '') return '';
			var tids = o.aData.tid.split('.');
			var rhtml = '<span title="'+o.aData.tid+'">'+tids[0]+'…</span>';
			return rhtml;
		    }
		}
	    ]
	});
    };
    var logbook = loadDatatable();

    $('#tasks-dt tbody').on('click', 'td', function () {
	var nTr = $(this).parents('tr')[0];
	var onTr = $(this).parent();
	var initStateOpen = logbook.fnIsOpen(nTr);
	$(".logbook tbody tr").removeClass('minus');
	$(".logbook tbody tr[estate='open']").each(function() {
	    logbook.fnClose( $(this)[0] );
	});
        if ( ! initStateOpen ) {
	    $(".logbook tbody tr").addClass('minus');
	    onTr.attr('estate', 'open').removeClass('minus');
            logbook.fnOpen( nTr, fnFormatDetails(logbook, nTr), 'details' );
        }
    } );
    
    // Add reset filter button
    $('.buttons')
	.append(
	    $('<a />',{id: 'reset-filters'})
		.attr('href','#')
		.attr('class','paginate_button paginate_button_disabled')
		.attr('title','[TEXT:TE:Client:reset filters and reload tasks]')
		.html('[TEXT:TE:Client:reset filters]')
	)
	.on('click', function() {
	    if (!serverApiOk) return;
	    $("thead tr .search_init").each( function() {
		switch ( $(this)[0].tagName ) {
		case 'SELECT':
		    $(this).val('');
		    break;
		case 'INPUT':
		    $(this).val('');
		    break;
		    default:
		}
	    });
	    var oSettings = logbook.fnSettings();
	    for(iCol = 0; iCol < oSettings.aoPreSearchCols.length; iCol++) {
		oSettings.aoPreSearchCols[ iCol ].sSearch = '';
	    }
	    logbook.fnDraw();
	});

    $("thead tr input, thead tr select")
	.on('click', function(event) {
	    event.stopPropagation();
	})
	.on('change', function() {
	    logbook.fnFilter( this.value, $("thead tr .search_init").index(this) );
	});

    $("thead tr input").
	on('keyup', function(evt) {
	    var evt = evt;
	    if (evt.keyCode == 13 && this.value!="")  {
		logbook.fnFilter( this.value, $("thead tr .search_init").index(this) );
	    }
	});


    /* 
     * Counters management
     *
     */
    var countersActive = serverApiOk;
    var countersTimer = null;

    $('#counters-state').prop('disabled', serverApiOk);

    var setCounter = function(counter, val) {
	if (val == undefined) val = '0';
	$('[data-counter="'+counter+'"]').html(val);
    };
     var setPCCounter = function(counter, val) {
	if (val == undefined) val = '0 %';
	$('[data-counter="'+counter+'"]').html( parseInt(val*100) + " %");
    };
   var resetCounters = function() {
	setCounter("interrupted", '-');
	setCounter("ko", '-');
	setCounter("done", '-');
	setCounter("processing", '-');
	setCounter("transferring", '-');
	setCounter("waiting", '-');
	setCounter("client-cur", '-');
	setCounter("client-max", '-');
	setCounter("sys-load-1", '-');
	setCounter("sys-load-2", '-');
	setCounter("sys-load-3", '-');
    };

    var runCounterUpdate = function() {
	if (countersActive) {
	    if (countersTimer == null) countersTimer = setTimeout( refreshCounters, countersUpdateInterval );
	} else {
	    resetCounters();
	    countersTimer = null;
	}
    };

    var refreshCounters = function() {

	$.ajax({
	    url: "?app=TENGINE_CLIENT&action=TENGINE_CLIENT_INFOS",
	    type: "GET",
	    success: function(data) {
		if (data.success) {
		    setCounter("interrupted", data.info.status_breakdown.I);
		    setCounter("ko", data.info.status_breakdown.K);
		    setCounter("done", data.info.status_breakdown.D);
		    setCounter("transferring", data.info.status_breakdown.T);
		    setCounter("processing", data.info.status_breakdown.P);
		    setCounter("waiting", data.info.status_breakdown.W);
		    setCounter("client-cur", data.info.cur_client);
		    setCounter("client-max", data.info.max_client);
		    setPCCounter("sys-load-1", data.info.load[0]);
		    setPCCounter("sys-load-2", data.info.load[1]);
		    setPCCounter("sys-load-3", data.info.load[2]);
		} else {
		    resetCounters();
		}
		runCounterUpdate();
	    },
	    error: function(data) {
		resetCounters();
		runCounterUpdate();
	    }
	});
	
    };

    $('#counters-state').on('change', function() {
	countersActive = ! $(this).is(':checked');
	runCounterUpdate();
    });


    serverVersion.check( function( sr ) {

	if (sr.status == 0) {
	    globalMessage.show("[TEXT:TE:Client:not fully supported server version, need server version ]"+" "+sr.required+".", 'warning');
	} else if (sr.status == -1) {
	    globalMessage.show("[TEXT:tengine_client_selftests:server communication error]"+"<br/>"+sr.message+".", 'error');
	} else {
	    serverApiOk = true;

	    countersActive = true;
	    $('#counters-state').prop('disabled', false);
	    refreshCounters();
    
	    $('#reset-filters').removeClass( 'paginate_button_disabled' );

	    logbook = loadDatatable();
	}
    });



});
