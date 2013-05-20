<?php

error_reporting(E_ALL);

require_once 'config/config.php';

function __autoload($class) {
	include 'module/'.$class.'.class.php';
}