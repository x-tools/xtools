
loadingImg = '<img src="//upload.wikimedia.org/wikipedia/commons/4/42/Loading.gif" />';
xconfigUrl = "//tools.wmflabs.org/xtools/agent/config.php";
cfgkey = 'xtoolcfg';

function settingsClear(){

	localStorage.removeItem( cfgkey );
	
	var request = $.ajax({
		  url: xconfigUrl,
		  type: "POST",
		  data: {
			  action: 'clearconfig'
			},
		  dataType: "html"
		});
	request.done( function(msg){
		$('#smessage').addClass( "label label-warning" ).text("Configuration wiped out." );
		settingsLoad();
	});
		 
	request.fail(function( jqXHR, textStatus ) {
		$('#smessage').addClass( "label label-danger" ).text("Request failed: " + textStatus );
	});
	
	setTimeout(function(){ $('#smessage').removeClass().text(''); }, 3000);
}

function settingsLoad(){
	var config = {};

	var confcentral = null;
	var request = $.ajax({
		  url: xconfigUrl,
		  type: "POST",
		  async: false,
		  data: {
			  action: 'loadconfig'
			},
		  dataType: "json"
		});
	request.done( function(msg){
		confcentral = msg;
	});
	
	request.fail(function() {
		confcentral = null;
	});
	
	var conflocal = localStorage.getItem( cfgkey );
	
	if( confcentral ){
		config = confcentral;
	}
	else if( conflocal){
		config = JSON.parse(conflocal);
	}
	else{
		config = defaultConfig;
	}
	
	var numwikis = config.trackwikis.length;
	var numpages = config.trackpages.length;
	
	config.trackwikis = config.trackwikis.join('\n');
	config.trackpages = config.trackpages.join('\n');
	
	$("#form_settings").unserialize(clearConfig);
	$("#form_settings").unserialize(config);
	
	$('#trackwikis').attr('rows', numwikis );
	$('#trackpages').attr('rows', numpages );
}

function settingsSave(){
	//1. get all congis for ease
	var datastring = $("#form_settings").serialize();
	//2. get some special configs (arrays), sanitize
	var config = $.unserialize(datastring);
	
	config.trackwikis = xtrim( config.trackwikis,10 );
	config.trackpages = xtrim( config.trackpages,50 );
	
	xdata = JSON.stringify(config);
	localStorage.setItem( cfgkey , xdata );
	sessionStorage.setItem( cfgkey , xdata );
	
	var addmsg = '';
	yy = localStorage.getItem( cfgkey );
	if (! yy){
		addmsg = ' - no localStorage available';
	}
	
	var request = $.ajax({
		  url: xconfigUrl,
		  type: "POST",
		  data: {
			  action: 'saveconfig',
			  config : xdata 
			},
		  dataType: "html"
		});
	
	request.done( function(msg){
		$('#smessage').addClass( "label label-success" ).text("Configuration saved" + addmsg );
		settingsLoad();
	});
		 
	request.fail(function( jqXHR, textStatus ) {
		$('#smessage').addClass( "label label-danger" ).text("Request failed: " + textStatus );
	});
	
	setTimeout(function(){ $('#smessage').removeClass().text(''); }, 3000);
}

function xtrim( arr, limit ){
	if (!arr){return; }
	var result = [];
	var lines = arr.split('\n');
	var i = 0;
	$.each(lines, function(){
		result[i] = this.trim() ;
		i++;
		if ( i >= limit ){ return false; }
	});
	
	return result;
}

function checkPages(gg){
	$('#checkresultsP').html(loadingImg);
	
	var pages = $('textarea[name=trackpages]').val();
	var xdata = JSON.stringify( xtrim(pages) );
	var request = $.ajax({
		  url: xconfigUrl,
		  type: "POST",
		  data: {
			  action: 'check',
			  pages : xdata 
			},
		  dataType: "json"
		});
	request.done( checkPagesResult );
		 
	request.fail(function( jqXHR, textStatus ) {
		$('#checkresultsP').text("Request failed: " + textStatus );
	});
}

function checkPagesResult( msg ){
	var table = $('<table></table>').addClass('table-striped table-condensed xt-table');
	$.each(msg, function(){
		entry = this;
		var row = $('<tr></tr>').html('\
				<td><a href="//'+ entry.url +'" >page link</a></td> \
				<td>'+ entry.pageid + '</td> \
				<td>'+ entry.ns + '</td> \
				<td>'+ entry.lastrev + '</td> \
				<td>'+ entry.datediff + '</td> \
				<td>'+ entry.lastuser + '</td> \
			');
		table.append(row);
	});
	$('#checkresultsP').html(table);
}

/**
 * https://developer.mozilla.org/en/docs/Web/Guide/API/DOM/Storage
 */
//if (!window.localStorage) {
//	
//	  window.localStorage = {
//	    getItem: function (sKey) {
//	      if (!sKey || !this.hasOwnProperty(sKey)) { return null; }
//	      return unescape(document.cookie.replace(new RegExp("(?:^|.*;\\s*)" + escape(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=\\s*((?:[^;](?!;))*[^;]?).*"), "$1"));
//	    },
//	    key: function (nKeyId) {
//	      return unescape(document.cookie.replace(/\s*\=(?:.(?!;))*$/, "").split(/\s*\=(?:[^;](?!;))*[^;]?;\s*/)[nKeyId]);
//	    },
//	    setItem: function (sKey, sValue) {
//	      if(!sKey) { return; }
//	      document.cookie = escape(sKey) + "=" + escape(sValue) + "; expires=Tue, 19 Jan 2038 03:14:07 GMT; path=/";
//	      this.length = document.cookie.match(/\=/g).length;
//	    },
//	    length: 0,
//	    removeItem: function (sKey) {
//	      if (!sKey || !this.hasOwnProperty(sKey)) { return; }
//	      document.cookie = escape(sKey) + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
//	      this.length--;
//	    },
//	    hasOwnProperty: function (sKey) {
//	      return (new RegExp("(?:^|;\\s*)" + escape(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=")).test(document.cookie);
//	    }
//	 };
//	 window.localStorage.length = (document.cookie.match(/\=/g) || window.localStorage).length;
//}

