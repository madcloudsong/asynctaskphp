<?php

require_once(__DIR__ . '/asynctaskphp.php');

spawn(function()
{
	echo '[a]';
	yield Timer::waitAsync(0.2);
	echo '[b]';
	yield Timer::waitAsync(0.2);
	
	$contents = (yield readFileAsync('http://php.net/'));
	var_dump(substr($contents, 0, 200));

	echo '[c]';
	yield Timer::waitAsync(0.2);
	echo '[d]';
});

spawn(function()
{
	foreach (xrange(0, 10) as $v)
	{
		echo "{$v}";
		yield Timer::waitAsync(0.3);
	}
});

spawn(function() {
	$contents = (yield readFileAsync('http://google.es/'));
	var_dump(substr($contents, 0, 200));
});



event_loop();
