$(function(e){

	/* Data Table */
	$('#hr-table').DataTable({
		"order": [[ 0, "desc" ]],
		order: [],
		columnDefs: [ { orderable: false, targets: [0, 2] } ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			
		}
	});
	/* End Data Table */

	/* Select2 */
	$('.select2').select2({
		minimumResultsForSearch: Infinity
	});

	
 });