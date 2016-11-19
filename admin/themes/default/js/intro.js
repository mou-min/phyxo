$(function() {
    $('.cluetip').cluetip({
	width: 300,
	splitTitle: '|',
	positionBy: 'bottomTop'
    });
    $('#check-upgrade').click(function(e) {
	$.ajax({
	    type: 'GET',
	    url: '../ws.php',
	    dataType: 'json',
	    data: { method: 'pwg.extensions.checkUpdates', format: 'json' },
	    timeout: 5000,
	    success: function (data) {
		if (data['stat'] != 'ok') {
		    return;
		}

		phyxo_update = data.result.phyxo_need_update;
		ext_update = data.result.ext_need_update;
		if ((phyxo_update || ext_update) && !$(".warnings").is('div')) {
		    $("#content").prepend('<div class="warnings"><i class="eiw-icon icon-attention"></i><ul></ul></div>');
		    if (phyxo_update) {
			$(".warnings ul").append('<li>'+phyxo_need_update_msg+'</li>');
		    }
		    if (ext_update) {
			$(".warnings ul").append('<li>'+ext_need_update_msg+'</li>');
		    }
		}
	    }
	});
	e.preventDefault();
    });
});
