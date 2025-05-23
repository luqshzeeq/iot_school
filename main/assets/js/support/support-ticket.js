$(function(e){

	//______summernote
	$('.summernote').summernote({
		placeholder: '',
		tabsize: 1,
		height: 200,
		toolbar: [
			['style', ['style']],
			['font', ['bold', 'underline', 'clear']],
			['color', ['color']],
			['para', ['ul', 'ol']],
			['insert', ['link', 'picture', 'video']],
			['view', ['fullscreen', 'codeview']],
		  ]
	});

	//______Vertical-scroll
	$(".item-list-scroll").bootstrapNews({
		newsPerPage: 4,
		autoplay: true,
		pauseOnHover: true,
		navigation: false,
		direction: 'down',
		newsTickerInterval: 2500,
		onToDo: function () {
			//console.log(this);
		}
	});

 });

 