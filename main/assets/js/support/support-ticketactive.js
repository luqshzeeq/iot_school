$(function(e){

	//________ Data Table
	$('#supportticket-active').DataTable({
		"paging":   false,
		searching: false,
		"info": false
	});

	//________ Select2
	$('.select2').select2({
		minimumResultsForSearch: Infinity,
		width:'100%'
	});


	//________ Datepicker
	$( '.fc-datepicker').datepicker({
		dateFormat: "dd M yy",
		monthNamesShort: [ "Jan", "Feb", "Mar", "Apr", "Maj", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dec" ],
		zIndex: 999998,
	});

 });

 