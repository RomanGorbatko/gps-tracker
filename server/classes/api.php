<?php
	define( 'ROOT_DIR', dirname(__FILE__) );

	include 'data.php';

	/**
	 * Класс-контроллер принимающий запросы с роутера.
	 * Обработка происходит в модели data.php
	 */
	class API extends Data {

		private $_return = array();

		public function __construct() {
			parent::__construct();
			$this->_return= array(
				'error' => 0
			);
		}

		public function test() {
			$this->_return['result'] = $_GET;
			return json_encode($this->_return);
		}

		public function sendRoutes() {
			if (isset($_POST['data'])) {
				$this->_return['result'] = $this->processData(json_decode($_POST['data'], true));
				echo json_encode($this->_return);
			}
		}
	}

?>