( function ( $ ) {
	"use strict";

	/*Bar-Chart */
	var ctx = document.getElementById('chartbar-statistics').getContext('2d');
	var myChart = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			datasets: [{
					label: 'Projects',
					data: [27, 18, 27, 23, 17, 19, 22.5, 19.5, 17.5, 18.5, 19.8, 27],
					borderWidth: 0,
					backgroundColor: '#2e3471',
					borderColor: '#2e3471',
					pointBackgroundColor: '#2e3471',
				},
				{
					label: 'Expenses',
					data: [29.5, 22, 23, 17, 20.5, 21, 24.8, 17, 15.8, 21, 22, 28.5],
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
					bottom: 20
				}
			},
			tooltips: {
				enabled: false,
			},
			scales: {
				xAxes: [{
					barValueSpacing :20,
					barDatasetSpacing : 2,
					barRadius: 15,
					stacked: false,
					categoryPercentage: 0.45,
					ticks: {
						beginAtZero: true,
						fontColor: "#8492a6",
					},
					gridLines: {
						color: "rgba(142, 156, 173,0.1)",
						display: false
					},

				}],
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
						stepSize: 5,
						suggestedMin: 0,
                		suggestedMax: 30,
						fontColor: "#8492a6",
					},
				}]
			},
			legend: {
				display: false
			}
		}
	});


	/*----- Advancedtask ------*/
	var options = {
		series: [62, 23, 15],
		chart: {
			height:280,
			type: 'donut',
		},
		dataLabels: {
			enabled: false
		},

		legend: {
			show: false,
		},
		 stroke: {
			show: true,
			width:0
		},
		plotOptions: {
		pie: {
			donut: {
				size: '80%',
				background: 'transparent',
				labels: {
					show: true,
					name: {
						show: true,
						fontSize: '29px',
						color:'#6c6f9a',
						offsetY: -10
					},
					value: {
						show: true,
						fontSize: '26px',
						color: undefined,
						offsetY: 16,
						formatter: function (val) {
							return val + "%"
						}
					},
					total: {
						show: true,
						showAlways: false,
						label: 'Total Analysis',
						fontSize: '22px',
						fontWeight: 600,
						color: '#373d3f',
						// formatter: function (w) {
						//   return w.globals.seriesTotals.reduce((a, b) => {
						// 	return a + b
						//   }, 0)
						// }
					  }

				}
			}
		}
		},
		responsive: [{
			breakpoint: 480,
			options: {
				legend: {
					show: false,
				}
			}
		}],
		labels: ["Design","Development" , "Service"],
		colors: ['#3366ff', '#fe7f00' , '#0dcd94'],
	};
	var chart = new ApexCharts(document.querySelector("#analysis"), options);
	chart.render();

	/*-----Expenses-----*/
	var myCanvas = document.getElementById("expenses");
	myCanvas.height="150";
    var myChart = new Chart( myCanvas, {
		type: 'line',
		data: {
			labels: ['2015', '2016', '2017', '2018', '2019', '2020'],
			datasets: [{
				label: 'Expenses',
				data: [15, 32, 15, 38, 18, 25,  22],
				backgroundColor: 'transparent',
				borderWidth: 3,
				borderColor: '#3366ff',
				hoverBorderColor: '#3366ff',
			}, {

			    label: '',
				data: [25, 28, 21, 33, 18, 36, 18],
				backgroundColor: '#262b5a',
				borderWidth: 3,
				borderColor:'#262b5a',
			}
		  ]
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
						min: 10,
						stepSize: 10,
						max: 40,
						fontColor: "#8492a6",
						userCallback: function(tick) {
							return tick.toString() + 'k';
						}
					},
				}],
				xAxes: [{
					ticks: {
						fontColor: "#8492a6",
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
	/*-----Expenses-----*/

	/* Data Table */
	$('.orders-table').DataTable({
		"paging":   false,
		searching: false,
		"info": false,
		"ordering": false
	});
	/* End Data Table */

	/* Data Table */
	$('.invoice-table').DataTable({
		"paging":   false,
		searching: false,
		"info": false,
	});
	/* End Data Table */

	/* Data Table */
	$('.projecttable').DataTable({
		"paging":   false,
		searching: false,
		"info": false,
		order: [],
		columnDefs: [ { orderable: false, targets: [1] } ],
	});
	/* End Data Table */

	/* Select2 */
	$('.select2').select2({
		minimumResultsForSearch: Infinity,
		width:'100%'
	});

	//______calendar
    $('.custom-calendar').pignoseCalendar({
		disabledDates:[
			'2021-01-20'
		],
		format: 'YYY-MM-DD',
	});

	//________ Datepicker
	$( '.fc-datepicker').datepicker({
		autoHide: true,
		zIndex: 999998,
	})

})( jQuery );

