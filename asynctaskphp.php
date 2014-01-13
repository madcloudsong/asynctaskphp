<?php

trait SingletonTrait
{
	static private $instance;

	public static function getInstance()
	{
		if (static::$instance == null) static::$instance = new static();
		return static::$instance;
	}
}

interface IDeferred
{
	function resolve($value = null);
	function reject(Exception $exception);
}

interface IPromise
{
	function then($callback);
}

class Promise implements IDeferred, IPromise
{
	private $completed = false;
	private $resolvedValue = null;
	private $rejectedException = null;
	private $callbacks = [];
	public $promise;
	
	static public function createDeferred()
	{
		return new Promise();
	}
	
	public function __construct()
	{
		$this->promise = $this;
	}
	
	public function resolve($value = null)
	{
		if ($this->completed) return;
		$this->completed = true;
		$this->resolvedValue = $value;
		$this->check();
	}
	
	public function reject(Exception $exception)
	{
		if ($this->completed) return;
		$this->completed = true;
		$this->rejectedException = $exception;
		$this->check();
	}
	
	public function then($callback)
	{
		$this->callbacks[] = $callback;
		$this->check();
	}
	
	private function check()
	{
		if ($this->completed)
		{
			while (count($this->callbacks) > 0)
			{
				$callback = array_shift($this->callbacks);
				$callback($this->resolvedValue, $this->rejectedException);
			}
		}
	}
	
	public function __toString()
	{
		return 'Promise';
	}
}

interface IEventLoop
{
	function setTimeout($callback, $timeMs);
	function setImmediate($callback);
	function setInterval($callback, $timeMs);
	function loop();
}

class EventBase
{
	use SingletonTrait;

	public $__handle;
	
	public function __construct()
	{
		$this->__handle = event_base_new();
	}
	
	public function __destruct()
	{
		//event_base_free($this->__handle);
	}

	public function addBuffer(EventBuffer $eventBuffer)
	{
		event_buffer_base_set($eventBuffer->__handle, $this->__handle);
	}

	public function loop()
	{
		event_base_loop($this->__handle);
	}
}

class Event
{
	public $__handle;

	public function __construct()
	{
		$this->__handle = event_new();
	}
	
	// EV_TIMEOUT, EV_SIGNAL, EV_READ, EV_WRITE and EV_PERSIST.
	public function set($fd, $events, $callback, $arg = NULL)
	{
		event_set($this->__handle, $fd, $events, $callback, $arg);
		event_base_set($this->__handle, EventBase::getInstance()->__handle);
		event_add($this->__handle);
	}
	
	public function dispose()
	{
		event_del($this->__handle);
	}
	
	public function __destruct()
	{
		$this->dispose();
		event_free($this->__handle);
	}
}

class EventTimer
{
	public $__handle;

	public function __construct()
	{
		$this->__handle = event_timer_new();
	}
	
	static public function setTimeout($callback, $timeMs)
	{
		$timer = new EventTimer();
		$timer->set(function() use ($timer, $callback) {
			$callback();
		}, $timeMs);
	}
	
	static public function setInterval($callback, $timeMs)
	{
		static::setTimeout(function() use ($callback, $timeMs) {
			static::setInterval($callback, $timeMs);
			$callback();
		}, $timeMs);
	}
	
	static public function setImmediate($callback)
	{
		static::setTimeout($callback);
	}
	
	private function set($callback, $timeMs)
	{
		$microSeconds = $timeMs * 1000;
		event_timer_set($this->__handle, $callback, null);
		event_base_set($this->__handle, EventBase::getInstance()->__handle);
		event_timer_add($this->__handle, $microSeconds);
	}
	
	public function __destruct()
	{
		event_free($this->__handle);
	}
}

class EventBuffer
{
	public $__handle;
	
	public function __construct($stream, $readCallback, $writeCallback, $errorCallback, $arg = NULL)
	{
		$this->__handle = event_buffer_new($stream, $readCallback, $writeCallback, $errorCallback, $arg);
		EventBase::getInstance()->addBuffer($this);
	}
	
	public function __destruct()
	{
		event_buffer_free($this->__handle);
	}
	
	public function setTimeout($readTimeout, $writeTimeout)
	{
		event_buffer_timeout_set($this->__handle, $readTimeout, $writeTimeout);
	}

	public function setWatermark($events, $lowmark, $highmark)
	{
		event_buffer_watermark_set($this->__handle, $events, $lowmark, $highmark);
	}
	
	public function setPriority($priority)
	{
		event_buffer_priority_set($this->__handle, $priority);
	}
	
	public function enable($events)
	{
		event_buffer_enable($this->__handle, $events);
	}

	public function disable($events)
	{
		event_buffer_disable($this->__handle, $events);
	}
	
	public function read($data_size)
	{
		return event_buffer_read($this->__handle, $data_size);
	}
}

class TcpSocketServerClient
{
	private $buffer;
	private $connection;

	public function __construct($connection)
	{
		$this->connection = $connection;
		$this->buffer = new EventBuffer($connection, [$this, '__read'], NULL, [$this, '__error']);
		$this->buffer->setTimeout(30, 30);
		$this->buffer->setWatermark(EV_READ, 0, 0xffffff);
		$this->buffer->setPriority(10);
		$this->buffer->enable(EV_READ | EV_PERSIST);
	}
	
	public function __error($__1, $error, $arg)
	{
		$this->buffer->disable(EV_READ | EV_WRITE);
		fclose($this->connection);
		unset($this->buffer, $this->connection);
	}

	public function __read($__1, $arg)
	{
		//print_r(stream_get_meta_data($this->connection));
		while ($read = $this->buffer->read(0x10000))
		{
			var_dump($read);
		}
	}
}

class TcpSocketServer
{
	public $__handle;
	private $event;
	private $id = 0;
	private $clients = [];

	public function __construct()
	{
	}
	
	public function listen($address, $port, $acceptCallback)
	{
		$this->__handle = stream_socket_server("tcp://{$address}:{$port}", $errno, $errstr);
		stream_set_blocking($this->__handle, 0);
		
		$this->event = new Event();
		$this->event->set($this->__handle, EV_READ | EV_PERSIST, [$this, '__accept']);
	}
	
	public function __accept($socket, $flag, $base)
	{
		$connection = stream_socket_accept($socket);
		stream_set_blocking($connection, 0);
		
		$client = new TcpSocketServerClient($connection);
	}
}

class Timer
{
	static public function waitAsync($seconds)
	{
		$deferred = Promise::createDeferred();
		setTimeout(function() use ($deferred) {
			$deferred->resolve();
		}, $seconds * 1000);
		return $deferred->promise;
	}
}

function readFileAsync($uri)
{
	$deferred = Promise::createDeferred();
	
	$f = @fopen($uri, 'rb');
	
	if ($f) {
		stream_set_blocking($f, 0);
		$event = new Event();
		$buffer = '';
		$event->set($f, EV_READ | EV_PERSIST, function() use ($event, $f, &$buffer, $deferred) {
			$buffer .= fread($f, 0x10000);
			$info = stream_get_meta_data($f);
			if ($info['eof'])
			{
				$event->dispose();
				$deferred->resolve($buffer);
			}
		});
	} else {
		$deferred->reject(new Exception("Can't open '$uri'"));
	}
	
	return $deferred->promise;
}

function setTimeout($callback, $timeMs)
{
	EventTimer::setTimeout($callback, $timeMs);
}

function setImmediate($callback)
{
	EventTimer::setImmediate($callback);
}

function setInterval($callback, $timeMs)
{
	EventTimer::setInterval($callback, $timeMs);
}

function spawn($functionGenerator)
{
	$deferred = Promise::createDeferred();

	$generator = $functionGenerator();
	
	$step = function() use ($generator, &$step, $deferred)
	{
		$promise = $generator->current();
		if ($promise instanceof IPromise)
		{
			$promise->then(function($result, $error) use ($generator, &$step)
			{
				if ($error != null) {
					$generator->throw($error);
				} else {
					$generator->send($result);
				}
				$step();
			});
		} else {
			if (!$generator->valid())
			{
				$deferred->resolve();
			}
		}
	};
	
	$step();
	
	return $deferred->promise;
}

function event_loop()
{
	EventBase::getInstance()->loop();
}

function xrange($min, $max)
{
	for ($n = $min; $n < $max; $n++) yield $n;
}

/*
class FakeEventLoop implements IEventLoop
{	
	use SingletonTrait;
	
	private $callbacks = [];
	private $key = 0;

	public function setTimeout($callback, $timeMs)
	{
		$this->callbacks[$this->key++] = [microtime(true) + $timeMs / 1000, $callback];
	}
	
	public function setImmediate($callback)
	{
		$this->setTimeout($callback, 0);
	}
	
	public function setInterval($callback, $timeMs)
	{
		$this->setTimeout(function() use ($callback, $timeMs) {
			$this->setInterval($callback, $timeMs);
			$callback();
		}, $timeMs);
	}

	public function loop()
	{
		while (true)
		{
			$currentTime = microtime(true);
			
			foreach (array_keys($this->callbacks) as $key)
			{
				$info = $this->callbacks[$key];
				if ($currentTime >= $info[0])
				{
					unset($this->callbacks[$key]);
					$callback = $info[1];
					$callback();
				}
			}
			
			//echo 1;
			usleep(20000); // 20ms
			
			if (count($this->callbacks) == 0) exit;
		}
	}
}
*/