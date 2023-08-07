<?php
    
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('max_execution_time', 300); // 5 minutes

    // For script timing
    $start = microtime(TRUE);

    require 'email-validator-fns.php';

    $debug = true;

    // basic test
    var_dump(validateEmail('invalid@emailformat'));
    var_dump(validateEmail('a@b.c'));

    // general test
    // var_dump(validateEmail('eshaanoexist@outlook.com'));
    // var_dump(validateEmail('hotgirl@hotmail.com'));

    // none-existing test
    var_dump(validateEmail('helloworld@gmail.com')); // test on live
    var_dump(validateEmail('example@yahoo.com')); // test on live

    // other domains test
    // var_dump(validateEmail('david@icloud.com'));
    // var_dump(validateEmail('ishallnotdiebutlive@icloud.com'));

    // other emails test
    // var_dump(validateEmail('no-reply@e.udemymail.com'));
    // var_dump(validateEmail('GENS@gtbank.com'));

    // role based test
    var_dump(validateEmail('security@mail.instagram.com')); // test on live
    var_dump(validateEmail('recruitment@wemabank.com'));

    $end = microtime(TRUE);
	echo ' Done in <strong>'.round($end - $start, 4)." seconds</strong>";

?>