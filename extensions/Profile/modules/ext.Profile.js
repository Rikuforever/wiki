$.post(
    mw.util.wikiScript(), {
        action: 'ajax',
        rs: 'wfConnectDB',
        rsargs: []
    }
).done( function( data ) {
	    $("#test").html(data);
    } 
);


$("#btn001").click(function() {
	alert("Button clicked.");
});

