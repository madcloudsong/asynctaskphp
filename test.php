<?php

require_once(__DIR__ . '/asynctaskphp.php');

spawn(function() {
	echo '[a]';
	yield Timer::waitAsync(0.2);
	echo '[b]';
	yield Timer::waitAsync(0.2);
	echo '[c]';
	yield Timer::waitAsync(0.2);
	echo '[d]';
});

spawn(function() {
	foreach (xrange(0, 10) as $v)
	{
		echo "{$v}";
		yield Timer::waitAsync(0.3);
	}
});

event_loop();

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
