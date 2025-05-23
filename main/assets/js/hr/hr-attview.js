$(function(e){

	//________ DataTable
	$('#hr-attendance1').DataTable({
		"order": [[ 0, 'desc' ], [ 1, 'desc' ]],
		columnDefs: [ 
			{orderable: false, targets: ['_all']} 
		 ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			
		}
	});
	/* End Data Table */
	
	//________ Datepicker
	$( ".fc-datepicker" ).datepicker({
		dateFormat: "dd MM yy",
		monthNamesShort: [ "Jan", "Feb", "Mar", "Apr", "Maj", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dec" ]
	});
	
	//________ Select2
	$('.select2').select2({
		minimumResultsForSearch: Infinity,
		width:'100%'
	});

 });