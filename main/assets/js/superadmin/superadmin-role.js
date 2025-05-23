$(function(e){

    //________ Data Table
	$('#superrole-list').DataTable( {
        order: [[2, 'asc']],
        rowGroup: {
            dataSrc: [2]
        },
        columnDefs: [ 
			{ orderable: false, targets: [0] } ,
			{targets: [ 2],
			visible: false,}
		],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			
		}
    } );

	//________ Select2
	$('.select2').select2({
		minimumResultsForSearch: Infinity
	});


	//______________
	$(".role").on("click", function(e){
		swal({
			title: "Are you sure?",
			text: "Once deleted, you will not be able to recover this file!",
			icon: "warning",
			buttons: true,
			dangerMode: true,
		})
		.then((willDelete) => {
			if (willDelete) {
			swal({
				title: "Success",
				text: "Successfully Updated",
				icon: "success",
			});
			} else {
			swal("Your  file is safe!");
			}
		});
	});
	
 });

 