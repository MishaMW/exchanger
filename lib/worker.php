<?php

final class Worker {

	public static function run($mysqli) {
		
		$res = mysqli_query($mysqli, 'SELECT * FROM information_schema.tables LIMIT  ' . MAX_RESULT);

		for ($count = 0; null !== ($row = mysqli_fetch_assoc($res)); $count++) {
			
			// здесь логика обработки данных
		}
		mysqli_free($res);

		return $count / MAX_RESULT;
	}
}
