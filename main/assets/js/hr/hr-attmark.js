$(function(e){

	//________ Data Table
	$('#hr-table').DataTable({
		columnDefs: [ { orderable: false, targets: [8] } ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			
		}
	});
	
	//________ Datepicker
	$( ".fc-datepicker" ).datepicker({
		dateFormat: "dd MM yy",
		monthNamesShort: [ "Jan", "Feb", "Mar", "Apr", "Maj", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dec" ]
	});
	$('.fc-datepicker').datepicker('setDate', 'today');

	
	//________ Select2
	$('.select2').select2({
		minimumResultsForSearch: Infinity
	});

	//________ Check all
	$('#checkAll').on('click', function() {
		if ($(this).is(':checked')) {
			$('input[type="checkbox"]').each(function() {
				$(this).closest('#hr-table').addClass('selected');
				$(this).attr('checked', true);
			});
		} else {
			$('input[type="checkbox"]').each(function() {
				$(this).closest('#hr-table').removeClass('selected');
				$(this).attr('checked', false);
			});
		}
	});

 });