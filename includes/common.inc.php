<?php
ini_set('display_errors','off');

require dirname(__FILE__) . '/../vendor/autoload.php';

define('PHPREDIS_ADMIN_PATH', dirname(__DIR__));

// These includes are needed by each script.
if(file_exists(PHPREDIS_ADMIN_PATH . '/includes/config.inc.php')){
  require_once PHPREDIS_ADMIN_PATH . '/includes/config.inc.php';
}else{
  require_once PHPREDIS_ADMIN_PATH . '/includes/config.sample.inc.php';
}
require_once PHPREDIS_ADMIN_PATH . '/includes/functions.inc.php';
require_once PHPREDIS_ADMIN_PATH . '/includes/page.inc.php';

if (isset($config['login'])) {
  require_once PHPREDIS_ADMIN_PATH . '/includes/login.inc.php';
}

//var_dump($config['servers']);
//echo "<br>";

$servername = $config['db_host'];
$username = $config['db_user'];
$password = $config['db_pass'];
$dbname = $config['db_name'];
 
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("获取服务列表失败,联系运维!");
} 
 
$sql = "SELECT * FROM servers";
$result = $conn->query($sql);

$c_servers = array();
$sum = array();
$n = array();
 
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
		$c_servers['name'] = $row['name'];
		$c_servers['host'] = $row['host'];
		$c_servers['port'] = (int)$row['port'];
		$c_servers['filter'] = $row['filter'];
		$c_servers['server_id'] = $row['server_id'];
		array_push($n, $c_servers['server_id']);
        //array_push($sum, $c_servers);
        $sum[$c_servers['server_id']] = $c_servers;
		$c_servers = array();
    }
} else {
    die("服务列表为空,请联系管理员!");
}
$conn->close();

$config['servers'] = $sum;

//var_dump($config['servers']);
//echo "<br>";
//die();

if (isset($login['servers'])) {
  $i = current($login['servers']);
} else {
  //$i = 0;
  $i = min($n);
}

if (isset($_GET['s']) && is_numeric($_GET['s']) && ($_GET['s'] < count($config['servers']))) {
  $i = $_GET['s'];
}

$server            = $config['servers'][$i];
$server['id']      = $i;
$server['charset'] = isset($server['charset']) && $server['charset'] ? $server['charset'] : false;

mb_internal_encoding('utf-8');


if (isset($login, $login['servers'])) {
  if (array_search($i, $login['servers']) === false) {
    die('You are not allowed to access this database.');
  }

  foreach ($config['servers'] as $key => $ignore) {
    if (array_search($key, $login['servers']) === false) {
      unset($config['servers'][$key]);
    }
  }
}


if (!isset($server['db'])) {
  if (isset($_GET['d']) && is_numeric($_GET['d'])) {
    $server['db'] = $_GET['d'];
  } else {
    $server['db'] = 0;
  }
}


if (!isset($server['filter'])) {
  $server['filter'] = '*';
}

// filter from GET param
if (isset($_GET['filter']) && $_GET['filter'] != '') {
    $server['filter'] = $_GET['filter'];
    if (strpos($server['filter'], '*') === false) {
      $server['filter'].= '*';
    }
}

if (!isset($server['seperator'])) {
  $server['seperator'] = $config['seperator'];
}

if (!isset($server['keys'])) {
  $server['keys'] = $config['keys'];
}

if (!isset($server['scansize'])) {
  $server['scansize'] = $config['scansize'];
}

if (!isset($server['serialization'])) {
  if (isset($config['serialization'])) {
    $server['serialization'] = $config['serialization'];
  }
}

if (!isset($config['hideEmptyDBs'])) {
  $config['hideEmptyDBs'] = false;
}

if (!isset($config['showEmptyNamespaceAsKey'])) {
  $config['showEmptyNamespaceAsKey'] = false;
}

// Setup a connection to Redis.
if(isset($server['scheme']) && $server['scheme'] === 'unix' && $server['path']) {
  $redis = new Predis\Client(array('scheme' => 'unix', 'path' => $server['path']));
} else {
  $redis = !$server['port'] ? new Predis\Client($server['host']) : new Predis\Client('tcp://'.$server['host'].':'.$server['port']);
}

try {
    $redis->connect();
} catch (Predis\CommunicationException $exception) {
    die('ERROR: ' . $exception->getMessage());
}

if (isset($server['auth'])) {
  if (!$redis->auth($server['auth'])) {
    die('ERROR: Authentication failed ('.$server['host'].':'.$server['port'].')');
  }
}


if ($server['db'] != 0) {
  if (!$redis->select($server['db'])) {
    die('ERROR: Selecting database failed ('.$server['host'].':'.$server['port'].','.$server['db'].')');
  }
}

?>
