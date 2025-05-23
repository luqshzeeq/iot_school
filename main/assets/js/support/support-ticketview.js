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

	//______summernote
	$('.editsummernote').summernote({
		placeholder: ' Lorem ipsum dolor sit amet, quis Neque porro quisquam est, nostrud exercitation ullamco laboris   commodo consequat.',
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

	
	// ______________ Attach Remove
	$(document).on('click', '[data-toggle="remove"]', function(e) {
		let $a = $(this).closest(".attach-supportfiles");
		$a.remove();
		e.preventDefault();
		return false;
	});

	// ______________Edit Summernote
	$(document).on("click", '.supportnote-icon', function () {    
		if(document) {
			$('body').toggleClass('add-supportnote');
			localStorage.setItem("add-supportnote", "True");
		}
		else {
			$('body').removeClass('add-supportnote');
			localStorage.setItem("add-supportnote", "false");
		}
	});
	$(document).on("click", '.dismiss-btn', function () {    
		$('body').removeClass('add-supportnote');
	});

	// ______________Edit Summernote
	$(document).on("click", '.reopen-button', function () {    
		if(document) {
			$('body').toggleClass('add-reopencard');
			localStorage.setItem("add-reopencard", "True");
		}
		else {
			$('body').removeClass('add-reopencard');
			localStorage.setItem("add-reopencard", "false");
		}
	});

 });

 