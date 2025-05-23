$(function(){

	//__________ Select2
	$('.select2').select2({
		minimumResultsForSearch: Infinity,
		width: '100%'
	});
	
	//__________ Select2 by showing the search
	$('.select2-show-search').select2({
        minimumResultsForSearch: '',
        placeholder: "Search",
        width: '100%'
    });
	
});