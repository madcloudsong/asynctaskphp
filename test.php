<?php

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
				$callback($this->rejectedException, $this->resolvedValue);
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

class EventLoop implements IEventLoop
{
	static public $instance;
	
	private $callbacks = [];
	private $key = 0;

	static public function getInstance()
	{
		if (static::$instance == null) static::$instance = new EventLoop();
		return static::$instance;
	}
	
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

function setTimeout($callback, $time)
{
	EventLoop::getInstance()->setTimeout($callback, $time);
}

function setImmediate($callback)
{
	EventLoop::getInstance()->setImmediate($callback);
}

function setInterval($callback, $time)
{
	EventLoop::getInstance()->setInterval($callback, $time);
}

function spawn($functionGenerator)
{
	$generator = $functionGenerator();
	
	//$step = null;
	
	$step = function() use ($generator, &$step)
	{
		$promise = $generator->current();
		if ($promise instanceof IPromise)
		{
			$promise->then(function($result) use ($generator, &$step)
			{
				$generator->send($result);
				$step();
			});
		}
	};
	
	$step();
}

/*
spawn(function() {
	echo '[a]';
	yield Timer::waitAsync(1);
	echo '[b]';
	yield Timer::waitAsync(1);
	echo '[c]';
	yield Timer::waitAsync(1);
	echo '[d]';
});

spawn(function() {
	echo '[1]';
	yield Timer::waitAsync(1);
	echo '[2]';
	yield Timer::waitAsync(1);
	echo '[3]';
	yield Timer::waitAsync(1);
	echo '[4]';
});
*/

/*
Timer::waitAsync(1)->then(function($result) {
	echo 'hello!';
});
*/

/*
setInterval(function() {
	echo 'hello!';
}, 100);
*/

//EventLoop::getInstance()->loop();
