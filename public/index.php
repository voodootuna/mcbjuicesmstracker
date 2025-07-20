<?php
require '../vendor/autoload.php';

Flight::set('flight.views.path', '../views');

require '../app/Database.php';
require '../app/SmsParser.php';
require '../app/routes.php';

Flight::start();