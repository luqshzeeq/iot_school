$(function(e){

	//________Data-Table
	$('#emp-attendance').DataTable({
		"order": [[ 0, "desc" ]],
		order: [],
		columnDefs: [ { orderable: false, targets: [0, 5] } ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',

		}
	});

	//________Select2
	$('.select2').select2({
		minimumResultsForSearch: Infinity,
		width:'100%'
	});

 });