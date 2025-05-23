$(function(e){

	//________ DataTable
	$('#hr-attendance').DataTable({
		"order": [[ 0, "desc" ]],
		columnDefs: [ 
			{bSortable: false, targets: ['_all']} 
		 ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			
		}
	});
	

	//________ DataTable
	$('#emp-attendance').DataTable({
		"order": [[ 0, "asec" ]],
		order: [],
		columnDefs: [ { orderable: false, targets: [5, 6] } ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			
		}
	});

	//________ Timepicker
	$('.timepicker').timepicker({
		showInputs: false,
	});
	
	//________ Datepicker
	$( ".fc-datepicker" ).datepicker({
		dateFormat: "dd MM yy",
		monthNamesShort: [ "Jan", "Feb", "Mar", "Apr", "Maj", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dec" ]
	});
	$('.fc-datepicker').datepicker('setDate', 'today');

 });