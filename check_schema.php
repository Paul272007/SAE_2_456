<?php
require 'vendor/autoload.php';
use Core\Config;
Config::load('config.json');
$db = Config::get('database');
$pdo = new PDO($db['dsn'], $db['user'], $db['password']);

$stmt = $pdo->query("SELECT column_name, data_type, nullable FROM all_tab_columns WHERE table_name = 'VIK_RESERVATION' OR table_name = 'VIK_ETAPE'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
