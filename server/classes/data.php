<?php

	include 'mysql.php';
	include 'Dijkstra.php';

	/**
	* Модель 
	*/
	class Data extends DB
	{	
		/**
		 * Массив шагов
		 * @var array
		 */
		private $_steps = array();

		/**
		 * deprecated
		 * Массив соседей вершин графа
		 * @var array
		 */
		private $_neighbor = array();

		/**
		 * Массив среднего индекса (по шкале от 0 до 9) загруженности
		 * дорожного движения на определенном участке.
		 * @var array
		 */
		private $_traffic = array();

		/**
		 * Массив связей вершин графа с указанием длины ребра
		 * @var array
		 */
		public $_list = array();

		/**
		 * Список вершин
		 * @var array
		 */
		public $_vertex = array();

		/**
		 * Вызов родительского конструктора класса DB,
		 * который инициализирует подключение к базе данных
		 * и работу с SQL запросами
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * Инициализация бэкенда
		 */
		public function processData($aData) 
		{
			// @TODO: Принадлежность точки к отрезку
			// SELECT * FROM steps  WHERE ((30.51243629999999 < max_long AND 30.51243629999999 > min_long) AND (50.467414250000004 < max_lat AND 50.467414250000004 > min_lat))
			foreach($aData as $iRoute => $aRoute) {
				$iId = $this->getRouteId($aRoute);
				$this->getStepsData($iId, $aRoute['steps']);
			}

			/**
			 * ВАЖНО!
			 * При указании других точек, отличных от дефолтных в полях приложения, 
			 * раскомментировать данную строку.
			 * Описание метода смотри ниже.
			 * ВАЖНО!
			 */
			// $this->simulateTracker();

			return $this->buildVertex();
		}

		/**
		 * Построение вершин графа
		 */
		public function buildVertex() {
			$aTraffic = $this->getTraffic();
			$this->_steps = $this->getStepsData();
			if ($aTraffic) {

				foreach ($aTraffic as $key => $value) {
					$this->_vertex[$key] = new vertex($value['step_id'] - 1);
				}

				foreach ($this->_steps as $key => $value) {
					$aStep = $this->_formatStartFinish($value);

					$this->_list[$key] = new SplDoublyLinkedList();
					foreach ($this->_steps as $iKey => $aValue) {
						$aValue = $this->_formatStartFinish($aValue);

						if ($aStep['finish_lat'] == $aValue['start_lat'] &&
							$aStep['finish_long'] == $aValue['start_long']) {
							$this->_list[$key]->push(array('vertex' => $this->_vertex[$aValue['id'] - 1], 'distance' => $aTraffic[$key]['level']));
						}
					}
					$this->_list[$key]->rewind();
				}

				return $this->_calcShortestPaths();
			}

			return false;
		}

		/**
		 * Если передан $iId шага - вернуть трафик на этом шаге,
		 * иначе вернуть весь трафик
		 */
		public function getTraffic($iId = null) {
			if (is_null($iId)) {
				return $this->_getAllTraffic();
			} else {
				return $this->_getTrafficByStepId($iId);
			}
		}

		/**
		 * Эмулирует сигналы с клиентских устройств
		 * для индексации трафика на определенном участке,
		 * использует данные уже имеющихся участков,
		 * каждый из которых разбит на 100 м.
		 */
		public function simulateTracker() {
			$aData = $this->getStepsData();
			foreach ($aData as $iKey => $aStep) {
				$iBars = $this->_stepToBars($aStep['distance']);
				for ($i = 0; $i <= $iBars; $i++) {
					$this->_setTrack($aStep['id'], $aStep['route_id'], rand(0, 9));
				}
			}
		}

		/**
		 * deprecated
		 * Находит соседние вершины графа и пишет их в базу
		 */
		public function setNeighbor() {
			$this->_steps = $this->_getAllSteps();

			foreach($this->_steps as $iKey => $aStep) {
				$this->_steps[$iKey] = $this->_formatStartFinish($aStep);
			}

			$this->_neighbor = $this->_searchNeighbor();
		}

		/**
		 * Возвращает Id маршрута или создает его
		 */
		public function getRouteId($aRoute) {
			$mRouteRow = $this->_getRoute($aRoute, false);

			if ($mRouteRow) {
				return (int) $mRouteRow;
			} else {
				return $this->_setRoute($aRoute);
			}
		}

		/**
		 * Если в функцию не передается ни одного параметра
		 * возвращает все отрезки, иначе проверяет маршрут
		 * в крайнем случае - возвращает false
		 */
		public function getStepsData($iId = null, $aSteps = null) {
			if (is_null($iId) && is_null($aSteps)) {
				return $this->_getAllSteps();
			} elseif (!is_null($iId) && !is_null($aSteps)) {
				return $this->checkSteps($iId, $aSteps);
			} else {
				return false;
			}
		} 

		/**
		 * Проверка отрезков.
		 * Возвращает все отрезки на маршруте, иначе пишет новый
		 */
		public function checkSteps($iId, $aSteps = null) {
			if ($this->_getStepsByRoute($iId, true)) {
				return $this->_getStepsByRoute($iId);
			} else {
				return $this->_setSteps($iId, $aSteps);
			}
		}

		/**
		 * Возвращает Id по заданным параметрам если $bIsId == false,
		 * иначе по id.
		 */
		private function _getRoute($mRoute, $bIsId = false) {
			if (!$bIsId) {
				$aRouteQuery = $this->row('SELECT id FROM routes WHERE start_lat = :sla AND start_long = :slon AND finish_lat = :fla AND finish_long = :flon AND distance = :di AND duration = :du', $this->_getParams($mRoute, 'route'));

				if (isset($aRouteQuery['id'])) {
					return $aRouteQuery['id'];
				}
			} else {
				$aRouteQuery = $this->row('SELECT id FROM routes WHERE id = :id', array('id' => $mRoute));

				if (isset($aRouteQuery['id'])) {
					return $aRouteQuery['id'];
				}
			}

			return false;
		}

		/**
		 * Возвращает дистанцию маршрута
		 */
		private function _getRouteDistance($iId) {
			return $this->query('SELECT distance FROM routes WHERE id = :id', array('id' => $iId));
		}

		/**
		 * устанавливает маршрут
		 */
		private function _setRoute($aRoute) {
			$this->query("INSERT INTO routes (start_lat, start_long, finish_lat, finish_long, distance, duration) VALUES(:sla, :slon, :fla, :flon, :di, :du)", $this->_getParams($aRoute, 'route'));

			return $this->lastInsertId();
		}

		/**
		 * Записывает треки в базу
		 */
		private function _setTrack($iStepId, $iTrackId, $iLevel) {
			$this->query("INSERT INTO track (step_id, route_id, level) VALUES(:sid, :rid, :l)", array('sid' => $iStepId, 'rid' => $iTrackId, 'l' => $iLevel));

			return $this->lastInsertId();
		}

		/**
		 * Удаляет отрезки с маршрута по его Id
		 */
		private function _removeStepsByRoute($iId) {
			return $this->query('DELETE FROM `steps` WHERE route_id = :rid', array('rid' => (int) $iId));
		}

		/**
		 * получает все отрезки
		 */
		private function _getAllSteps() {
			return $this->query('SELECT * FROM steps');
		}

		/**
		 * Возвращает средний индекс загруженности по всем шагам
		 */
		private function _getAllTraffic() {
			return $this->query('SELECT `step_id`, `route_id`, ROUND(AVG(`level`)) as `level` FROM track GROUP BY step_id;');
		}

		/**
		 * Возвращает средний индекс загруженности шага по его Id
		 */
		private function _getTrafficByStepId($iStepId) {
			return $this->row('SELECT `step_id`, `route_id`, ROUND(AVG(`level`)) as `level` FROM track  WHERE `step_id` = :sid', array('sid' => (int) $iStepId));
		}

		/**
		 * Если bCount передается отличным от FALSE
		 * возвращает кол-во шагов на маршруте, иначе
		 * массив отрезков на маршруте по его Id
		 */
		private function _getStepsByRoute($iId, $bCount = false) {
			if ($bCount) {
				$aCount = $this->row('SELECT COUNT(*) as num FROM steps WHERE route_id = :rid', array('rid' => (int) $iId));
				return $aCount['num'];
			} else {
				$aStepsQuery = $this->query('SELECT * FROM steps WHERE route_id = :rid', array('rid' => (int) $iId));
				if (count($aStepsQuery) > 0) {
					return $aStepsQuery;
				}
			}

			return false;
		}

		/**
		 * Удаляет шаги с маршрута по его Id 
		 */
		private function _setSteps($iId, $aSteps) {
			$this->_removeStepsByRoute($iId);

			$iCount = 0;
			foreach ($aSteps as $iKey => $aStep) {
				$iCount++;
				$aParams = array('rid' => (int) $iId) + $this->_getParams($aStep, 'step');

				$this->query("INSERT INTO steps (route_id, max_lat, max_long, min_lat, min_long, start_finish, distance) VALUES(:rid, :mala, :malon, :mila, :milon, :sf, :di)", $aParams);
			}

			return $iCount;
		}

		/**
		 * Метод инициализирующий алгоритм Дейкстры,
		 * и обрабатывающий его результаты.
		 * 
		 */
		private function _calcShortestPaths() {
			// print_r($this->_list);
			// print_r($this->_list);
			new calcShortestPaths($this->_vertex[0], $this->_list);
			$aEnd = end($this->_vertex);
			$aRoute = array();
			$aRouteDistance = array();

			foreach ($aEnd->path as $key => $value) {
				if (!isset($aRoute[$this->_steps[$value]['route_id']])) {
					$aRoute[$this->_steps[$value]['route_id']]['level'] = 0;
				}

				if (!$aRoute[$this->_steps[$value]['route_id']]['distance']) {
					$aDistance = $this->_getRouteDistance($this->_steps[$value]['route_id']);
					$aRoute[$this->_steps[$value]['route_id']]['distance'] = $aDistance[0]['distance'];
				}
				
				$aRoute[$this->_steps[$value]['route_id']]['level']++; //$this->_steps[$value - 1];
			}

			return array(
				'all' => $aRoute, 
				'max' => array_search(max($aRoute), $aRoute)
			);
		}

		/**
		 * deprecated
		 * Ищет соседей вершин 
		 */
		private function _searchNeighbor() {
			$aResult = array();
			$aCache = array();
			foreach ($this->_steps as $iKey => $aStep) {				
				foreach ($this->_steps as $iKey2 => $aStep2) {
					if ($aStep['start_lat'] == $aStep2['start_lat'] && 
						$aStep['start_long'] == $aStep2['start_long'] &&
						$aStep['finish_lat'] != $aStep2['finish_lat'] &&
						$aStep['finish_long'] != $aStep2['finish_long'] &&
						$aStep['route_id'] != $aStep2['route_id']) {
						$aResult[] = array(
							'start' => $aStep2,
							'end' => $aStep
						);
					}
				}
			}

			return $a;
		}

		/**
		 * Генерирует массив параметра для валидации в SQL запросах
		 */
		private function _getParams($aData, $sType = 'route') {
			if ($sType === 'route') {
				return array(
					'sla' => $aData['start_location']['A'],
					'slon' => $aData['start_location']['F'],
					'fla' => $aData['end_location']['A'],
					'flon' => $aData['end_location']['F'],
					'di' => $aData['distance']['value'],
					'du' => $aData['duration']['value']
				);
			} elseif ($sType === 'step') {
				return $this->_formatMinMax($aData);
			}
		}


		/**
		 * Форматирует данные координат в зависимости от минимального-максимального значения.
		 * Нужен для поиска отрезка по точке находящийся в нужном квардате.
		 */
		private function _formatMinMax($aData) {
			if ($aData['start_location']['A'] >= $aData['end_location']['A']) {
				return array(
					'mala' => $aData['start_location']['A'],
					'malon' => $aData['start_location']['F'],
					'mila' => $aData['end_location']['A'],
					'milon' => $aData['end_location']['F'],
					'sf' => 's:f',
					'di' => $aData['distance']
				);
			} else {
				return array(
					'mala' => $aData['end_location']['A'],
					'malon' => $aData['end_location']['F'],
					'mila' => $aData['start_location']['A'],
					'milon' => $aData['start_location']['F'],
					'sf' => 'f:s',
					'di' => $aData['distance']
				);
			}
		}

		/**
		 * Обратное от _formatMinMax формаирование в Начальную-Конечную точку
		 */
		private function _formatStartFinish($aStep) {
			$aMeta = explode(':', $aStep['start_finish']);
			if ($aMeta[0] == 's') {
				return array(
					'id' => $aStep['id'],
					'route_id' => $aStep['route_id'],
					'start_lat' => $aStep['max_lat'],
					'start_long' => $aStep['max_long'],
					'finish_lat' => $aStep['min_lat'],
					'finish_long' => $aStep['min_long']
				);
			} elseif ($aMeta[0] == 'f') {
				return array(
					'id' => $aStep['id'],
					'route_id' => $aStep['route_id'],
					'start_lat' => $aStep['min_lat'],
					'start_long' => $aStep['min_long'],
					'finish_lat' => $aStep['max_lat'],
					'finish_long' => $aStep['max_long']
				);
			}
		}

		/**
		 * Конвертация метров в километры
		 */
		private function _toKm($iMeters) {
			return ($iMeters / 1000);
		}

		/**
		 * Разделение участка на треки по 100 метров
		 * Нужен для симуляции трекинга
		 */
		private function _stepToBars($iMeters) {
			return round($iMeters / 100);
		}
	}

?>