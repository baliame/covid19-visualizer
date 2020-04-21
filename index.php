<html>
<head>
	<title>COVID-19 Visualizer</title>
	<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>
	<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/gh/xcash/bootstrap-autocomplete@v2.3.4/dist/latest/bootstrap-autocomplete.min.js"></script>
	<link href="https://stackpath.bootstrapcdn.com/bootswatch/4.4.1/darkly/bootstrap.min.css" rel="stylesheet" integrity="sha384-rCA2D+D9QXuP2TomtQwd+uP50EHjpafN+wruul0sXZzX/Da7Txn4tB9aLMZV4DZm" crossorigin="anonymous">
	<style type="text/css">
.column {
	float: left;
	margin-left: 10px;
	margin-right: 10px;
}
.navbar-nav li:hover > ul.dropdown-menu {
    display: block;
}
.dropdown-submenu {
    position:relative;
}
.dropdown-submenu>.dropdown-menu {
    top:0;
    left:100%;
    margin-top:-6px;
}
	</style>
</head>
<body>
	<script>
		var $ = jQuery;

		var colors = [
			'#6200EE',
			'#CF6679',
			'#03DAC6',
			'#602080',
			'#A7D129',
			'#0B5269',
			'#BEF992',
			'#FFC045',
			'#FF4D00',
			'#9C9C9C',
			'#F2A365',
			'#46B5D1',
			'#ED8D8D',
			'#E1E099',
			'#978D58',
		];

		var gridColor = 'rgba(192, 192, 192, 0.3)';
		var gridColorHighlight = 'rgba(192, 192, 192, 0.7)';
		Chart.defaults.global.defaultFontColor = gridColorHighlight;

		var dataTable = <?php
			$ret = array();
			$sqlite = new SQLite3("cases.sqlite");
			$result = $sqlite->query("SELECT * FROM data_points");
			while (true) {
				$row = $result->fetchArray(SQLITE3_ASSOC);
				if (empty($row)) {
					break;
				}
				if (empty($ret[$row["location"]])) {
					$ret[$row["location"]] = array();
				}
				$ret[$row["location"]][$row["day"]] = array(
					"cases" => $row["cases"],
					"deaths" => $row["deaths"],
					"date" => $row["date"],
				);
			}
			echo(json_encode($ret))
		?>;

		var builder_countries = [];
		var dataCache = {};

		var rebuild_builder_chart = function() {
			var countries = builder_countries;
			var type = $('input[name="data-points"]:checked').val()

			if (!countries.length) {
				$('#builder-chart').collapse('hide');
				return
			}

			$('#builder-chart-canvas').remove();
			$('#builder-chart').append('<canvas id="builder-chart-canvas"></canvas>');

			var ctx = document.getElementById('builder-chart-canvas').getContext('2d');
			var i = 0;
			var cfg = {
				type: 'line',
				data: {
					labels: [],
					datasets: [],
				},
				options: default_options('Comparison of ' + type + ' in countries', 'Days since first infection', '# of ' + type),
			}
			var labels = [];
			for (country of countries) {
				var data = dataTable[country];
				var dPoints = [];
				var clabels = Object.keys(data);
				if (labels.length < clabels.length) {
					labels = clabels;
				}
				for (let [day, daily] of Object.entries(data)) {
					dPoints.push(daily[type]);
				}
				cfg['data']['datasets'].push({
					label: country,
					backgroundColor: colors[i],
					borderColor: colors[i],
					data: dPoints,
					fill: false,
				});
				i++;
			}
			cfg['data']['labels'] = labels;
			new Chart(ctx, cfg);
			$('#builder-chart').collapse('show');
		};

		var generate_location_chart = function() {}

		var default_options = function(titleText, xAxis, yAxis) {
			return {
				title: {
					display: true,
					text: titleText,
				},
				tooltips: {
					mode: "index",
					intersect: false,
				},
				hover: {
					mode: "nearest",
					intersect: true,
				},
				scales: {
					xAxes: [{
						display: true,
						gridLines: {
							color: gridColor,
							zeroLineColor: gridColorHighlight,
						},
						scaleLabel: {
							display: true,
							labelString: xAxis,
						},
					}],
					yAxes: [{
						display: true,
						gridLines: {
							color: gridColor,
							zeroLineColor: gridColorHighlight,
						},
						scaleLabel: {
							display: true,
							labelString: yAxis,
						},
					}],
				},
				legend: {
					labels: {
						fontColor: gridColorHighlight,
					}
				}
			}
		}

		var collapser = function() {
			var controls = $(this).data('ariaControls');
			$('.collapse.show.top-level').each(function() {
				if ($(this).attr('id') != controls) {
					$(this).collapse('hide');
				}
			});
		}

		var lazyloader = function() {
			const country = $(this).data('location');
			const dc = dataCache[country];
			if (dc['rendered']) {
				return;
			}
			const cases = dc['cases'];
			const caseChanges = dc['caseChanges'];
			const caseGrowth = dc['caseGrowth'];
			const deaths = dc['deaths'];
			const deathChanges = dc['deathChanges'];
			const deathGrowth = dc['deathGrowth'];
			const ctx = dc['ctx'];
			const ctx_case_chg = dc['ctx_case_chg'];
			const ctx_case_growth = dc['ctx_case_growth'];

			var country_chart = new Chart(ctx, {
				type: 'line',
				data: {
					labels: dc['labels'],
					datasets: [{
						label: 'Cases',
						backgroundColor: colors[0],
						borderColor: colors[0],
						data: cases,
						fill: false
					}, {
						label: 'Deaths',
						backgroundColor: colors[1],
						borderColor: colors[1],
						data: deaths,
						fill: false
					}]
				},
				options: default_options(country + " total cases and deaths", "Days since first infection", "# of people"),
			});
			var case_change_chart = new Chart(ctx_case_chg, {
				type: 'line',
				data: {
					labels: dc['labels'],
					datasets: [{
						label: 'New cases',
						backgroundColor: colors[0],
						borderColor: colors[0],
						data: caseChanges,
						fill: false
					}, {
						label: 'New deaths',
						backgroundColor: colors[1],
						borderColor: colors[1],
						data: deathChanges,
						fill: false
					}]
				},
				options: default_options(country + " daily change in cases/deaths", "Days since first infection", "Change in # of cases/deaths"),
			});
			var case_growth_chart = new Chart(ctx_case_growth, {
				type: 'line',
				data: {
					labels: dc['labels'],
					datasets: [{
						label: 'Growth in new cases',
						backgroundColor: colors[0],
						borderColor: colors[0],
						data: caseGrowth,
						fill: false
					}, {
						label: 'Growth in new deaths',
						backgroundColor: colors[1],
						borderColor: colors[1],
						data: deathGrowth,
						fill: false
					}]
				},
				options: default_options(country + " daily growth in cases/deaths", "Days since first infection", "% growth in cases/deaths per day"),
			});
			dc['rendered'] = true;
		};

		var rebuild_country_list = function() {
			console.log('rebuild')
			$('#country-list-container').empty();

			for (country of builder_countries) {
				$('#country-list-container').append('<div class="country-list-country-container"><span class="country-name">' + country + '</span><a class="country-remove btn btn-danger" role="button" data-location="'+ country +'">x</a></div>')
			}

			rebuild_builder_chart();
		}

		$(document).ready(function() {
			$('#navlink-builder').click(collapser);
			$('input[name="data-points"]').change(rebuild_builder_chart)

			$(document).on("keydown", "form", function(event) {
			    return event.key != "Enter";
			});

			for (let j = 65; j <= 90; j++) {
				var letter = String.fromCharCode(j)
				if (letter == 'X') {
					continue
				}
				$('#navbar-country-container').append('<li class="dropdown-submenu"><a href="#" class="dropdown-toggle dropdown-item" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><span class="nav-label">' + letter + '</span><span class="caret"></span></a><ul class="dropdown-menu" id="letter-' + letter + '"></ul></li>')
			}

			var clist = []
			for (let [country, data] of Object.entries(dataTable)) {
				clist.push(country)
			}
			clist.sort()
			$('.autocomplete-country').autoComplete({
				resolver: 'custom',
				minLength: 1,
				preventEnter: true,
				events: {
					search: function(query, callback, orig) {
						var results = []
						for (country of clist) {
							if (country.toLowerCase().startsWith(query.toLowerCase())) {
								results.push(country)
							}
						}
						callback(results)
					},
				},
			});
			$(document).on("click", ".country-remove", function() {
				country = $(this).data('location');
				builder_countries = builder_countries.filter(function(value, index, arr) {
					return country.toLowerCase() !== value.toLowerCase()
				});
				rebuild_country_list();
			});
			$('.autocomplete-country').on('autocomplete.select', function(evt, item) {
				$(this).attr('value', item);
			});
			$('#country-add-btn').click(function() {
				var c = $('#country-add').attr('value');
				var found = false;
				for (let country of clist) {
					if (country.toLowerCase() === c.toLowerCase()) {
						c = country;
						found = true;
						break;
					}
				}
				if (!found) {
					return;
				}
				$('#country-add').attr('value', '');
				$('#country-add').autoComplete('clear');
				for (let country of builder_countries) {
					if (country.toLowerCase() === c.toLowerCase()) {
						return;
					}
				}
				builder_countries.push(c);

				rebuild_country_list();
			});
			for (country of clist) {
				var data = dataTable[country]
				const c_id = 'chart-' + country.replace(/ /g, "-");
				const a_id = c_id + '-collapser';
				const canvas_id = c_id + '-cases';
				const case_changes_id = c_id + '-case-changes';
				const case_growth_id = c_id + '-case-growth';
				const checkbox_id = c_id + '-include';
				const checkbox_wrapper_id = checkbox_id + '-wrapper';
				var letter = country.substring(0, 1);
				$('#letter-' + letter).append('<li><a id="'+ a_id + '" class="dropdown-item" data-location="' + country + '" data-toggle="collapse" href="#' + c_id + '" aria-expanded="false" aria-controls="' + c_id + '">' + country + '</a></li>');
				$('#' + a_id).click(collapser);
				$('#charts').append('<div id="' + c_id + '" class="country-container collapse top-level"></div>');
				//$('#country-list-container').append('<div id="' + checkbox_wrapper_id + '"><input type="checkbox" value="' + country + '" id="' + checkbox_id + '" class="country-selector"/><label for="' + checkbox_id + '">' + country + '</label></div>');
				$('#' + c_id).append('<canvas id="' + canvas_id + '"></canvas>')
				$('#' + c_id).append('<canvas id="' + case_changes_id + '"></canvas>')
				$('#' + c_id).append('<canvas id="' + case_growth_id + '"></canvas>')
				var ctx = document.getElementById(canvas_id).getContext('2d');
				var ctx_case_chg = document.getElementById(case_changes_id).getContext('2d');
				var ctx_case_growth = document.getElementById(case_growth_id).getContext('2d');
				var cases = []
				var caseChanges = []
				var caseGrowth = []
				var deaths = []
				var deathChanges = []
				var deathGrowth = []
				for (let [day, daily] of Object.entries(data)) {
					if (caseChanges.length) {
						const caseChg = daily['cases'] - cases[cases.length - 1];
						caseChanges.push(caseChg);
						caseGrowth.push(cases[cases.length - 1] > 0 ? caseChg / cases[cases.length - 1] :  0);
						const deathChg = daily['deaths'] - deaths[deaths.length - 1];
						deathChanges.push(deathChg);
						deathGrowth.push(deaths[deaths.length - 1] > 0 ? deathChg / deaths[deaths.length - 1] : 0);
					} else {
						caseChanges.push(daily['cases']);
						caseGrowth.push(0);
						deathChanges.push(daily['deaths']);
						deathGrowth.push(0);
					}
					cases.push(daily['cases'])
					deaths.push(daily['deaths'])
				}
				dataCache[country] = {
					cases, caseChanges, caseGrowth, deaths, deathChanges, deathGrowth, ctx, ctx_case_chg, ctx_case_growth,
					labels: Object.keys(data),
					rendered: false,
				}
				$('#' + a_id).click(lazyloader);
			}
		});
	</script>
	<div id="controls">
		<nav class="navbar navbar-expand-lg navbar-light bg-light">
			<a class="navbar-brand" href="#">COVID-19</a>
			<ul class="navbar-nav mr-auto">
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" id="navbar-countries" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Locations</a>
					<ul class="dropdown-menu" aria-labelledby="navbar-countries" id="navbar-country-container">
					</ul>
				</li>
				<li class="nav-item">
					<a id="navlink-builder" class="nav-link" data-toggle="collapse" href="#builder" aria-expanded="false" aria-controls="builder">Chart builder</a>
				</li>
			</ul>
		</nav>
	</div>
	<div id="charts">
		<div id="builder" class="builder-container collapse top-level">
			<div id="builder-configuration-toggle">
				<a href="#builder-configuration" id="builder-configuration-toggler" class="btn btn-secondary" role="button" data-toggle="collapse" aria-expanded="false" aria-controls="builder-configuration">Configuration</a>
			</div>
			<div id="builder-configuration" class="collapse show">
				<form id="builder-form">
					<div id="country-list-container-wrapper" class="column">
						<h3>Countries</h3>
						<input type="text" class="autocomplete-country" autocomplete="off" placeholder="Add country" name="country-add" id="country-add" value="" />
						<a href="#" class="btn btn-success" role="button" id="country-add-btn">Add</a>
						<div id="country-list-container">
						</div>
					</div>
					<div id="data-points-wrapper" class="column">
						<h3>Data</h3>
						<div id="data-points-container">
							<div>
								<input type="radio" value="cases" id="radio-cases" name="data-points" class="datapoint-selector" checked />
								<label for="radio-cases">Cases</label>
							</div>
							<div>
								<input type="radio" value="deaths" id="radio-deaths" name="data-points" class="datapoint-selector" />
								<label for="radio-deaths">Deaths</label>
							</div>
						</div>
					</div>
					<div style="clear:both"></div>
				</form>
			</div>
			<div id="builder-chart" class="collapse">
				<canvas id="builder-chart-canvas"></canvas>
			</div>
		</div>
	</div>
</body>
</html>