<?php
namespace Matsubo\GrowthForecast\Client;
/**
 *  Mac OS X client for GrowthForecast
 *
 */
class MacOS
{
  private $host   = 'http://localhost:5125';
  private $logger = null;
  private $service = '';
  private $section = '';
  private $devices = array();
  private $pdo = null;
  /**
   * __construct
   *
   * @access public
   * @return void
   */
  public function __construct($service, $section)
  {
    $this->service = $service;
    $this->section = $section;
  }
  /**
   * setHost
   *
   * @param string $host
   * @access public
   * @return void
   */
  public function setHost($host)
  {
    $this->host = $host;
  }
  /**
   * setDevices
   * 
   * @param array $devices
   * @access public
   * @return void
   */
  public function setDevices($devices)
  {
      $this->devices = $devices;
  }
  /**
   * setPDO
   *
   * @param PDO $pdo
   * @access public
   * @return void
   */
  public function setPDO($pdo)
  {
      $this->pdo = $pdo;
  }
  /**
   * setLogger
   *
   * @param Logger $logger
   * @access public
   * @return void
   */
  public function setLogger($logger)
  {
    $this->logger = $logger;
  }
  /**
   * execute
   *
   * @access public
   * @return void
   */
  public function execute()
  {
    $this->apacheProcess();
    $this->mysql();
    $this->disk();
    $this->vmstat();
    $this->bandwidth();
    $this->loadAverage();
    $this->hardwareTemp();
    $this->uptime();
  }


  /**
   * apacheProcess
   *
   * @access public
   * @return void
   */
  public function apacheProcess()
  {
    exec("ps -Ax|grep httpd|grep -v grep | wc -l", $output);

    $this->send('apache_process_count', array('number'  => trim($output[0])));
  }
  /**
   * mysql
   *
   * @access public
   * @return void
   */
  public function mysql()
  {
      if (!$this->pdo) {
          return;
      }

      $pstmt = $this->pdo->query('show processlist');
      $this->send('mysql_processlist', array('number'  => $pstmt->rowCount()));



      $pstmt = $this->pdo->query('show status');
      while ($row = $pstmt->fetch(\PDO::FETCH_ASSOC)) {
          if (!in_array($row['Variable_name'], array(
              'Qcache_free_blocks',
              'Qcache_free_memory',
              'Qcache_hits',
              'Qcache_inserts',
              'Qcache_lowmem_prunes',
              'Qcache_not_cached',
              'Qcache_queries_in_cache',
              'Qcache_total_blocks',
          ))) {
              continue;
          }
          $this->send('mysql_'.$row['Variable_name'], array('number'  => $row['Value']));
      }
  }



  /**
   * vmstat
   *
   * @access public
   * @return void
   */
  public function vmstat()
  {
    exec("vm_stat -c 1 | grep -v Statistics", $output);

    foreach ($output as $line) {
      $line = preg_replace('/ +/', ' ', $line);
      $columns = explode(":", $line);

      $id    = str_replace(array('*',' '), '-', trim($columns[0]));
      $value = str_replace('.', '', trim($columns[1]));

      $this->send('vm_stat_'.$id, array('number'  => $value));
    }
  }

  /**
   * bandwidth
   *
   * @access public
   * @return void
   */
  public function bandwidth()
  {
    exec("netstat -i -b -n |grep -v Name|grep Link", $output);

    foreach ($output as $line) {
      $line = preg_replace('/[0-9a-f]+:[0-9a-f:]+:[0-9a-f]+/', '', $line);
      $line = preg_replace('/ +/', ' ', $line);

      $columns = explode(" ", $line);

      $id = str_replace('*', '', trim($columns[0]));

      $this->send('bandwidth_in_' .$id, array('number'  => $columns[5]));
      $this->send('bandwidth_out_'.$id, array('number'  => $columns[8]));
    }
  }

  /**
   * hardwareTemp
   *
   * @access public
   * @return void
   */
  public function hardwareTemp()
  {
    exec("/Applications/TemperatureMonitor.app/Contents/MacOS/tempmonitor -c -l -a", $output);

    foreach ($output as $line) {
      $columns = explode(":", $line);

      $id = str_replace(array('(', ')', ' '), '-', trim($columns[0]));
      $temp = str_replace(array(' ','C'), '', trim($columns[1]));

      if ($temp == '-') {
        continue;
      }

      $this->send('temp_'.$id, array('number'  => $temp));
    }
  }
  /**
   * disk
   *
   * @access public
   * @return void
   */
  public function disk()
  {
    exec("df -k | grep dev | grep -v devfs", $output);

    foreach ($output as $line) {
      $line = preg_replace('/ +/', ' ', $line);
      $columns = explode(" ", $line);
      $disk_id = str_replace(array('/', '(', '('), '-', $columns[8]);

      if (!in_array($columns[0], $this->devices)) {
        continue;
      }

      $this->send('disk_'.$disk_id, array('number'  => $columns[2]));
    }
  }
  /**
   * loadAverage
   *
   * @access public
   * @return void
   */
  public function loadAverage()
  {
    exec('uptime', $output);

    if (!preg_match('/([0-9\.]+) ([0-9\.]+) ([0-9\.]+)$/', $output[0], $matches)) {
        return;
    }

    $this->send('la_1',  array('number'  => $matches[1] * 100));
    $this->send('la_5',  array('number'  => $matches[2] * 100));
    $this->send('la_15', array('number' => $matches[3] * 100));
  }
  /**
   * uptime
   *
   * @access public
   * @return void
   */
  public function uptime()
  {
    exec("/usr/sbin/sysctl kern.boottime", $output);

    if (!preg_match('/sec = ([0-9]+)/', $output[0], $matches)) {
      return;
    }

    $options = array(
      'number' => time() - $matches[1]
    );
    $this->send('uptime', $options);
  }
  /**
   * send
   *
   * @param string $graph
   * @param array $options
   * @access private
   * @return void
   */
  private function send($graph, $options)
  {
    if ($this->logger) {
      $this->logger->info($graph, $options);
    }

    $option = '';
    foreach ($options as $key => $value) {
      $option .= ' ' . $key . '=' . $value;
    }
    $command = sprintf('curl -s -F %s %s/api/%s/%s/%s', $option, $this->host, $this->service, $this->section, $graph);

    exec($command, $output);

    if ($this->logger) {
      $this->logger->info($command, $output);
    }

  }
}
