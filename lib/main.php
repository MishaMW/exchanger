<?php

/**
 * Exchanger
 *
 **/
final class Main {

	// Флаг работы демона
	private static $run = true;

	// Инстанс терминала
	public static $screen = null;

	/**
     * Основной цикл, который обрабатывает выполнение процесса
	*/
	public static function loop() : void {

		$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		
		do {
			echostr("BEGINNING\n", "green");

			$load = Worker::run($db);

			if (DAEMON_FORK) {

				$sleep = MAX_SLEEP + $load * (MIN_SLEEP - MAX_SLEEP);
				
				cli_set_process_title(DAEMON_NAME . ': ' . round(100 * $load, 1) . '%');
				echostr("Sleep for "); echostr($sleep, "yellow"); echostr(" seconds\n\n");
				sleep($sleep);
				
			} else if (0 == $load) {
				break;
			}
			
		} while (self::$run);

		mysqli_close($db);
	}

	/**
     * Регистрация окружения
	*/
	public static function registerEnv() : void {
		file_put_contents(DAEMON_PID, getmypid());

		posix_setuid(DAEMON_UID);
		posix_setgid(DAEMON_GID);
		
		self::_openConsole(posix_ttyname(STDOUT));

		fclose(STDIN);
		fclose(STDOUT);
		fclose(STDERR);
	}

	/**
     * Запуск консоли
	*/
	private static function _openConsole($screen) : void {

		if (!empty($screen) && false !== ($fd = fopen($screen, "c"))) {
			self::$screen = $fd;
		}
	}

	/**
     * Свой обработчик сигналов
	*/
	public static function _handleSignal($signo) : void {

		switch ($signo) {
			case SIGTERM:
				self::log(E_NOTICE, 'Received SIGTERM, dying...');
				self::$run = false;
				return;
			case SIGHUP:
				self::log(E_NOTICE, 'Received SIGHUP, rotate...');
				Worker::rotate();
				return;
			case SIGUSR1:

				if (null !== self::$screen) {
					@fclose(self::$screen);
				}

				self::$screen = null;

				if (preg_match('|pts/([0-9]+)|', `who`, $out) && !empty($out[1])) {
					self::_openConsole('/dev/pts/' . $out[1]);
				}
		}
	}

	/**
     * Регистрация своего обработчика сигналов
	*/
	public static function registerSignal() : void {

		pcntl_signal(SIGTERM, 'Main::_handleSignal');
		pcntl_signal(SIGHUP,  'Main::_handleSignal');
		pcntl_signal(SIGUSR1, 'Main::_handleSignal');
	}

	/**
     * Свой обработчик ошибок
	*/
	public static function handleError($errno, $errstr, $errfile, $errline, $errctx) : bool {

		if (error_reporting() == 0) {
			return false;
		}

		Main::log($errno, $errstr . " on line " . $errline . "(" . $errfile . ") -> " . var_export($errctx, true));

		return true;
	}

	/**
     * Логирование
	*/
	public static function log($code, $msg, $var = null) : void {

		static $codeMap = array(
            E_NOTICE  => "Notice",
			E_WARNING => "Warning",
			E_ERROR   => "Error"
		);

		$msg = date('[Y-m-d H:i:s] ') . $codeMap[$code] . ': ' . $msg;

		if (null !== $var) {

			$msg.= "\n";
			$msg.= var_export($var, true);
			$msg.= "\n";
			$msg.="\n";
		}
		file_put_contents(DAEMON_LOG, $msg . "\n", FILE_APPEND);
	}
}

