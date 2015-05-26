var App = {
	/**
	 * Статические настройки
	 */
	config: {
		map: {
			defaults: {
				lat: 50.4721445,
				lng: 30.51823749999994,
				zoom: 12,
				disableDefaultUI: true,
				mapTypeId: google.maps.MapTypeId.ROADMAP,
				timeout: 6000, 
				maximumAge: 50000,
				provideRouteAlternatives: true,
				maxRoute: 3
			}
		},
		colors: [
			'#000000', '#fc27c1', '#20e0e5'
		]
	},
	/**
	 * Массив начальной и конечных точек
	 */
	routeAddress: [],
	/**
	 * Google Map API объект
	 */
	map: null,
	/**
	 * Google Map API объект
	 */
	directionsDisplay: null,
	/**
	 * Google Map API объект
	 */
	directionsService: null,
	/**
	 * Google Map API объект
	 */
	stepDisplay: null,
	/**
	 * deprecated
	 * Массив маркеров, установленных на средине каждого маршрута
	 */
	markerArray: [],
	/**
	 * Массив полилиний для отрисовки маршрутов
	 */
	polyLine: [],
	/**
	 * Массив данных о возможных маршрутах (шаги, расстояние, координаты).
	 * Заполняется после отрисовки polyLine и отправляется на сервер.
	 */
	data: [],
	/**
	 * Инициализация JS-клиента
	 */
	init: function() {
		var _this = App;

		console.log('Method: Map.init()');  

		// if(navigator.network.connection.type == Connection.NONE) {
  //           $("#home_network_button").text('No Internet Access').attr("data-icon", "delete").button('refresh');
  //       }

        $("#startTracking_start").live('click', _this.initTrack);

	},
	/**
	* Срабатывает после нажатия на кнопку "Start Tracking"
	*/
	initTrack: function() {
		var _this = App;

		console.log('Method: Map.initTrack()');

		_this.routeAddress.push($("#from").val());
        _this.routeAddress.push($("#to").val());

        _this.initMap();
	},
	/**
	* Отрисовывает карту в соответствующем разделе,
	* используя ранее указаные адреса
	*/
	initMap: function() {
		var _this = App;

		console.log('Method: Map.initMap()');
		location.hash = '#map';

		$('#map').die('pageshow'); 
		$('#map').live('pageshow', function (event) {

			console.log('pageshow');
		    var ti = setTimeout(function() {
		    	var div = document.getElementById("map_canvas");
		    	

		    	_this.map = null;
				_this.map = new google.maps.Map(div, {
					zoom: _this.config.map.defaults.zoom,
					center: new google.maps.LatLng(_this.config.map.defaults.lat, _this.config.map.defaults.lng),
					mapTypeId: _this.config.map.defaults.mapTypeId,
					timeout: _this.config.map.defaults.timeout, 
					maximumAge: _this.config.map.defaults.maximumAge,
					disableDefaultUI: _this.config.map.defaults.disableDefaultUI
				});

				google.maps.event.addListenerOnce(_this.map, 'idle', function(){
				    console.log('Event: plugin.google.maps.event.MAP_READY');

					_this.directionsService = new google.maps.DirectionsService();
					_this.directionsDisplay = new google.maps.DirectionsRenderer();
					_this.stepDisplay = new google.maps.InfoWindow();

					var request = {
		                origin: _this.routeAddress[0],
		                destination: _this.routeAddress[1],
		                travelMode: google.maps.TravelMode.DRIVING,
		                provideRouteAlternatives: _this.config.map.defaults.provideRouteAlternatives
		            };

		            _this.directionsService.route(request, function(response, status) {
		                if (status == google.maps.DirectionsStatus.OK) {
		                    _this.checkRoutes(response);
		                    // _this.showSteps(response);
		                }
		            });
				});
				clearTimeout(ti);
		    }, 400);

			if ($("#traffic").is(':checked')) {
				_this.calculateOptimalRoute();
			} else {
				// alert('false');
			}

			$('#traffic').click(function () {
			    if (this.checked) {
			    	_this.calculateOptimalRoute();
			    }
			});

			$('#grid ul').html(' ');
			$('#traffic').prop('checked', '');
		});
	},
	/**
	* Отрабатывает после активации чекбокс-а "Использовать трафик",
	* инициирует отправку данных о маршрутах на сервер.
	*/
	calculateOptimalRoute: function() {
		var _this = App;
		console.log('Method: Map.calculateOptimalRoute()');


		_this.server.sendRoutesData(_this.data);

		$.mobile.loading("show", {
            text: "Рассчитываем оптимальный маршрут",
            textVisible: true,
            theme: 'a',
        });
	},
	/**
	* Callback-функция.
	* Срабатывает после успешного ответа сервером.
	*
	* Рисует оптимальный маршрут на карте,
	* обновляет информацию в информационном листе.
	*/
	showOptimalRoute: function(response) {
		var _this = App;
		var response = JSON.parse(response);

		if (!response.error) {
			$.mobile.loading('hide');

        	var result = response.result,
        		optimal = 0;
            _this.polyLine.forEach(function(polyline, i) {
            	if (i == (result.max - 1)) {
            		optimal = i;
            		return;	            	
            	} else {
            		for(var index in result.all) {
						if (polyline.directions.routes[polyline.routeIndex].legs[0].distance.value == result.all[index].distance) {
							$('#li' + i + ' span:eq(1)').text(' (Интекс трафика: ' + result.all[index].level + ')');
							delete result.all[index];
						}
					}

					_this.polyLine[i].setOptions({polylineOptions: { strokeColor: '#212046' }});

            	}

            	_this.polyLine[i].setMap(_this.map);
            });

            if (optimal !== 0) {
            	_this.polyLine[optimal].setOptions({polylineOptions: { strokeColor: '#07c', strokeWeight: 9 }});
            	_this.polyLine[optimal].setMap(_this.map);
            	$('.color_route').css({backgroundColor: '#212046'});
            	$('#li' + optimal + ' span:eq(0)').css({backgroundColor: '#07c'});
            	$('#li' + optimal + ' span:eq(1)').text(' (Интекс трафика: ' + response.result.all[response.result.max + 1].level + ')');
            }
        }
	},
	/**
	* Рисует все маршруты без учета трафика,
	* используя данные полученные от Google Maps Api.
	*
	* Заполняет массив data[] данными для последующей
	* отрпавки на сервер (в случае если будет активен чекбокс).
	* 
	* Инициирует информационный лист.
	*/
	checkRoutes: function(response) {
		var _this = App;
		console.log('Method: Map.checkRoutes()');

        for (var i = 0; i < response.routes.length; i++) {
            if ( i > (_this.config.map.defaults.maxRoute - 1) ) continue;

            _this.polyLine[i] = new google.maps.DirectionsRenderer({
                map: _this.map,
                directions: response,
                routeIndex: i,
                polylineOptions: {strokeColor: _this.config.colors[i]}
            });

            var steps = [];
            response.routes[i].legs[0].steps.forEach(function(a, stepI) {
            	steps[stepI] = {
            		start_location: a.start_location,
            		end_location: a.end_location
            	};
            });

            _this.data.push({
            	distance: response.routes[i].legs[0].distance,
            	duration: response.routes[i].legs[0].duration,
            	start_location: response.routes[i].legs[0].start_location,
            	end_location: response.routes[i].legs[0].end_location,
            	steps: steps,
            });

            $('#grid ul').append('<li id="li' + (i) + '"><span class="color_route" style="background: ' + _this.config.colors[i] + '"># ' + (i + 1) + '</span> Расстояние: ' + response.routes[i].legs[0].distance.text + '<span></span></li>');
        }
	},
	/**
	* deprecated
	* Рисует маркеры в центре всех маршрутов.
	*/
	showSteps: function(response) {
		var _this = App;
		console.log('Method: Map.showSteps()');

		var myRoute = response.routes;
        for (var i = 0; i < myRoute.length; i++) {
            var routeCenter = myRoute[i].legs[0].steps[Math.round(myRoute[i].legs[0].steps.length / 2)];
            var marker = new google.maps.Marker({
                position: routeCenter.start_point,
                map: _this.map
            });
            _this.attachInstructionText(marker, routeCenter.instructions);
            _this.markerArray[i] = marker;
        }
	},
	/**
	* deprecated
	* Устонавливает текст и событие по клику на маркер
	*/
	attachInstructionText: function(marker, text) {
		var _this = App;
		console.log('Method: Map.attachInstructionText()');

        google.maps.event.addListener(marker, 'click', function() {
            _this.stepDisplay.setContent(text);
            _this.stepDisplay.open(_this.map, marker);
        });
	},
	/**
	* Объект-модель для отправки данных на сервер
	*/
	server: {
		/**
		* Отправляет на сервер данные собранные в массиве data[].
		* В случае успешного ответа сервера, вызовет callback-метод 
		* _this.showOptimalRoute
		*/
		sendRoutesData: function(data) {
			var _this = App,
				__this = App.server;

	        api.ajaxPost('sendRoutes', {data: JSON.stringify(data)}, _this.showOptimalRoute, function(err) {
                console.log(err);
            });
		}
	}
};