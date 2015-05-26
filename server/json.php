<?php 
	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	// error_reporting(-1);
	
	include './classes/api.php';
	// header('Content-Type: application/json');
	header('Access-Control-Allow-Origin: *'); 

	/**
	 * Роутер. Принимает и обрабатывает данные, с клиента посредством AJAX-запросов. 
	 */
	if (isset($_GET['f'])) {

		$oApi = new Api();
		if (method_exists($oApi, $_GET['f'])) {
			echo $oApi->$_GET['f']();
		} else {
			echo json_encode(
				array(
					'error'=> 1, 
					'message' => 'метод не найден'
				)
			);
		}
	} else {
		echo json_encode(
			array(
				'error'=> 1, 
				'message' => 'нужно передать имя метода'
			)
		);
	}
?>