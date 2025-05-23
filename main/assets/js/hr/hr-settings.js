$(function(e){

    // ______________Select2
    $('.select2-show-search').select2({
        minimumResultsForSearch: '',
        placeholder: "Search",
        width: '100%'
    });

    // ______________Timepicker
	$('.timepicker').timepicker({
		showInputs: false,
	});

     // ______________Color-Picker
	$('#colorpicker').spectrum({
		color: '#000'
	});
    $("#showAlpha").spectrum({
        showPalette: true,
        showSelectionPalette: true,
        showInput: true,
        preferredFormat: "hex",
        color:'#ff0000'
    });

 });

 