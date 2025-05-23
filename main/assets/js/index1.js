$(function(e){

	/*LIne-Chart */
	var ctx = document.getElementById("chartLine").getContext('2d');
	var myChart = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			datasets: [{
				label: 'TOTAL BUDGET',
				data: [20,17,27,23,17,19,23,17,13,28,22,27],
				borderWidth: 2,
				backgroundColor: '#ccd9ff',
				borderColor: '#ccd9ff',
				pointBackgroundColor: '#ccd9ff',
				pointRadius: 0,
				type: 'bar',
			},
			{

				label: 'AMOUNT USED',
				data: [28,22,21,18,13,22,24,18,16,21,18,24],
				borderWidth: 3,
				backgroundColor: '#3366ff',
				borderColor: '#3366ff',
				pointBackgroundColor: '#3366ff',
				pointRadius: 0,
				type: 'bar',
				borderDash: [7,3],

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
						beginAtZero: true,
						stepSize: 5,
						suggestedMin: 5,
						suggestedMax: 30,
						fontColor: "#8492a6",
					},
				}],
				xAxes: [{
					barValueSpacing :-2,
					barDatasetSpacing : 0,
					barRadius: 15,
					stacked: false,
					categoryPercentage: 0.3,
					barPercentage: 0.8,
					ticks: {
						beginAtZero: true,
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

	/* Chartjs (#sales-summary) */
	var myCanvas = document.getElementById("sales-summary");
	myCanvas.height="300";
    var myChart = new Chart( myCanvas, {
		type: 'bar',
		data: {
			labels: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri" ,"Sat"],
			datasets: [{
				label: 'This Month',
				data: [27, 50, 28,50,18,30,22],
				backgroundColor: '#3366ff',
				borderWidth: 2,
				hoverBackgroundColor: '#3366ff',
				hoverBorderWidth: 0,
				borderColor: '#3366ff',
				hoverBorderColor: '#3366ff',
				borderDash: [5,2],
			}, {
			   label: 'Last Month',
				data: [68, 57, 53,58,23,75,28 ],
				backgroundColor: '#fe7f00',
				borderWidth: 2,
				hoverBackgroundColor: '#fe7f00',
				hoverBorderWidth: 0,
				borderColor: '#fe7f00',
				hoverBorderColor: '#fe7f00',
			},{
			   label: 'Last Month',
				data: [100, 78, 68,95,0,98, 58],
				backgroundColor: '#dbe2fc',
				borderWidth: 2,
				hoverBackgroundColor: '#dbe2fc',
				hoverBorderWidth: 0,
				borderColor: '#dbe2fc',
				hoverBorderColor: '#dbe2fc',
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
						beginAtZero: true,
						stepSize: 25,
						suggestedMin: 0,
						suggestedMax: 100,
						fontColor: "#8492a6",
						userCallback: function(tick) {
							return tick.toString() + '%';
						}
					},
				}],
				xAxes: [{
                    barPercentage: 0.15,
					barValueSpacing :0,
					barDatasetSpacing : 0,
					barRadius: 0,
					stacked: true,
					ticks: {
						beginAtZero: true,
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

	/*----- Employees ------*/
	var options = {
		series: [74, 35],
		chart: {
			height:300,
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
						label: 'Total',
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
			options: {
				legend: {
					show: false,
				}
			}
		}],
		labels: ["Male","Female"],
		colors: ['#3366ff', '#fe7f00'],
	};
	var chart = new ApexCharts(document.querySelector("#employees"), options);
	chart.render();

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
		//timeZone : -1 //
	});

 });