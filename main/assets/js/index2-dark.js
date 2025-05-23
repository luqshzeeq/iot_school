$(function(e){
	/*Bar-Chart */
	var ctx = document.getElementById("chartbar").getContext('2d');
	var myChart = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			datasets: [{
				label: 'TOTAL BUDGET',
				data: [27,17,19,23,17,19,23,17,13,28,22,27],
				borderWidth: 0,
				backgroundColor: '#2e3471',
				borderColor: '#2e3471',
				pointBackgroundColor: '#2e3471',
			},
			{

				label: 'AMOUNT USED',
				data: [28,22,21,18,13,22,24,18,16,21,18,24],
				borderWidth: 0,
				backgroundColor: '#3366ff',
				borderColor: '#3366ff',
				pointBackgroundColor: '#3366ff',

			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			layout: {
				padding: {
					left: 0,
					right: 0,
					top: 0,
					bottom: 0
				}
			},
			tooltips: {
				enabled: false,
			},
			scales: {
				yAxes: [{
					gridLines: {
						display: true,
						drawBorder: false,
						zeroLineColor: 'rgba(142, 156, 173,0.1)',
						color: "rgba(142, 156, 173,0.1)",
					},
					scaleLabel: {
						display: false,
					},
					ticks: {
						min: 5,
						stepSize: 5,
						max: 30,
						fontColor: "#8492a6",
						fontFamily: 'Poppins',
					},
				}],
				xAxes: [{
					barValueSpacing :-2,
					barDatasetSpacing : 0,
					barRadius: 15,
					stacked: false,
					categoryPercentage: 0.38,
					barPercentage: .8,
					ticks: {
						beginAtZero: true,
						fontColor: "#8492a6",
						fontFamily: 'Poppins',
					},
					gridLines: {
						color: "rgba(142, 156, 173,0.1)",
						display: false
					},

				}]
			},
			legend: {
				display: false
			},
			elements: {
				point: {
					radius: 0
				}
			}
		}
	});

	//______calendar
    $('.calendar').pignoseCalendar();

	/* Data Table */
	$('#assigntask').DataTable({
		order: [],
		columnDefs: [ { orderable: false, targets: [0, 5] } ],
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',

		}
	});
	/* End Data Table */

	//________ Datepicker
	$( ".fc-datepicker" ).datepicker({
		dateFormat: "dd M yy",
		monthNamesShort: [ "Jan", "Feb", "Mar", "Apr", "Maj", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dec" ]
	});

	//________ Timepiocker
	$('#tpBasic').timepicker();

	//________ Countdonwtimer
	$("#clocktimer").countdowntimer({
		currentTime : true,
		size : "md",
		borderColor : "transparent",
		backgroundColor : "transparent",
		fontColor : "#313e6a",
		// timeZone : "+1"
	});

	//Daterangepicker with Callback
	$('input[name="singledaterange"]').daterangepicker({
		singleDatePicker: true,
	});
	$('input[name="daterange"]').daterangepicker({
		opens: 'left'
	  }, function(start, end, label) {
		console.log("A new date selection was made: " + start.format('MMMM D, YYYY') + ' to ' + end.format('MMMM D, YYYY'));
	});

	$('#daterange-categories').change(function() {
		$('.leave-content').hide();
		$('#' + $(this).val()).show();
	});

	/* Select2 */
	$('.select2').select2({
		minimumResultsForSearch: Infinity
	});

 });