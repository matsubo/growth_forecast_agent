<?php
namespace Matsubo\GrowthForecast\Client;
/**
 *  Mac OS X client for GrowthForecast
 */
class MacOS
{
  private $host = '';
  private $logger = null;
  /**
   * __construct 
   * 
   * @access public
   * @return void
   */
  public function __construct()
  {
  }
  /**
   * setHost 
   * 
   * @param mixed $host 
   * @access public
   * @return void
   */
  public function setHost($host)
  {
    $this->host = $host ? $host : 'http://localhost:5125';
  }
  /**
   * setLogger 
   * 
   * @param mixed $logger 
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
    $this->disk();
    $this->vmstat();
    $this->bandwidth();
    $this->loadAverage();
    $this->hardwareTemp();
    $this->uptime();
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

      $this->send('/api/host/teraren/vm_stat_'.$id, array('number'  => $value));
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


      $this->send('/api/host/teraren/bandwidth_in_' .$id, array('number'  => $columns[5]));
      $this->send('/api/host/teraren/bandwidth_out_'.$id, array('number'  => $columns[8]));
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

      $this->send('/api/host/teraren/temp_'.$id, array('number'  => $temp));
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

      $devices = array(
        '/dev/disk2',
        '/dev/disk3s2',
        '/dev/disk4s2',
      );

      if (!in_array($columns[0], $devices)) {
        continue;
      }



      $this->send('/api/host/teraren/disk_'.$disk_id, array('number'  => $columns[2]));
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
    exec("uptime", $output);

    preg_match('/([0-9\.]+) ([0-9\.]+) ([0-9\.]+)$/', $output[0], $matches);

    $this->send('/api/host/teraren/la_1', array('number'  => $matches[1] * 100));
    $this->send('/api/host/teraren/la_5', array('number'  => $matches[2] * 100));
    $this->send('/api/host/teraren/la_15', array('number' => $matches[3] * 100));
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
        $this->send('/api/host/teraren/uptime', $options);
  }
  /**
   * send 
   * 
   * @param mixed $path 
   * @param mixed $options 
   * @access private
   * @return void
   */
  private function send($path, $options)
  {
        /*
        print_r($path);
        print "\n";
        print_r($options);
         */

    $option = '';
    foreach ($options as $key => $value) {
      $option .= ' ' . $key . '=' . $value;
    }
    $command = sprintf('curl -s -F %s %s%s', $option, $this->host, $path);

    return exec($command, $output);
  }
}


