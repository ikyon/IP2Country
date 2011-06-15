<?php

// ip2country.php - Version 1.0
// https://github.com/ikyon/IP2Country

// Example code, uncomment it to test the class
/*
$ip = '209.85.149.104';
$ip2country = new ip2country();
$ip2country->update_db();
$ip2country->ip_country($ip);
echo $ip.' is registred in '.$ip2country->country.' ('.$ip2country->ctry.'), registry is '.$ip2country->registry."\n";
echo $ip.' belongs to AS'.$ip2country->ip_asn($ip)."\n";
*/

class ip2country 
{
  public function __construct()
  {
    $this->mysql_server = 'localhost';  // Change if your MySQL server does not use localhost
    $this->mysql_user = '';  // Fill in MySQL user name
    $this->mysql_pass = '';  // Fill in MySQL password
    $this->mysql_db = '';  //Fill in MySQL database name
  }

  public function update_db()
  {
    //IP2Country database updates only once per day
    $md5 = file_get_contents('http://ikyon.se/code/ip2country.sql.gz.md5');
    if(!file_exists('ip2country.sql.gz.md5')||file_get_contents('ip2country.sql.gz.md5')!=$md5)
    {
      $sql = file('compress.zlib://http://ikyon.se/code/ip2country.sql.gz', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $this->mysqli = new mysqli($this->mysql_server, $this->mysql_user, $this->mysql_pass, $this->mysql_db) or die();
      $tables = array('countries', 'ip2country');
      foreach($tables as $table)
      {
        $result = $this->mysqli->query('SHOW TABLES LIKE \''.$table.'\'');      
        if($result->num_rows==1)
        {
          $this->mysqli->query('LOCK TABLES '.$table.' LOW_PRIORITY WRITE');
          $this->mysqli->query('TRUNCATE '.$table);      
        }
      }
      foreach($sql as $query)
      {
        //Be paranoid, check that queries are valid
        if(preg_match('/^CREATE TABLE IF NOT EXISTS([a-z0-9\(\)`=,_\s]+);$/i',$query)||
        preg_match('/^INSERT INTO `ip2country` \(`ip_start`, `ip_end`, `ctry`, `registry`\) VALUES \(([0-9]+), ([0-9]+), \'([A-Z]+)\', \'([a-z]+)\'\);$/',$query)||
        preg_match('/^INSERT INTO `countries` \(`country`, `ctry`\) VALUES \(\'([A-Za-z\s\(\);:\\\\\'\.\-]+)\', \'([A-Z]+)\'\);$/',$query))
        {
          $this->mysqli->query($query);
        }
        else
        {
          die();  //Invalid query
        }
      }
      $this->mysqli->query('UNLOCK TABLES');
      file_put_contents('ip2country.sql.gz.md5',$md5);
    }
  }

  public function ip_asn($ip)
  {
    if($this->ip_valid($ip))
    {
      //http://www.team-cymru.org/Services/ip-to-asn.html
      $results = @dns_get_record($this->ip_reverse($ip).'.origin.asn.cymru.com', DNS_TXT);
      if($results)
      {
        $results_a = explode('|',$results[0]['txt']);
        $result = trim($results_a[0]);
        if(is_numeric($result))
        {
          return $result;
        }
      }
    }
    return false;
  }

  public function ip_country($ip)
  {
    if($this->ip_valid($ip))
    {
      $ip = sprintf("%u", ip2long($ip));
      $this->mysqli = new mysqli($this->mysql_server, $this->mysql_user, $this->mysql_pass, $this->mysql_db) or die();
      $result = $this->mysqli->query('SELECT ctry, registry FROM ip2country WHERE ip_start <= '.$ip.' AND ip_end >= '.$ip.' LIMIT 1');
      if($result->num_rows==1)
      {
        $row = $result->fetch_object();
        $this->ctry = $row->ctry;
        $this->registry = $row->registry;
        $result = $this->mysqli->query('SELECT country FROM countries WHERE ctry = \''.$this->ctry.'\' LIMIT 1');
        if($result->num_rows==1)
        {
          $row = $result->fetch_object();
          $this->country = $row->country;
        }
      }
      $this->mysqli->close();
    }
  }

  public function ip_valid($ip)
  {
    $ip_array = explode(".",$ip);

    if(sizeof($ip_array)==4&&is_numeric($ip_array[0])&&$ip_array[0]<=255&&$ip_array[0]>=0&&is_numeric($ip_array[1])&&$ip_array[1]<=255&&$ip_array[1]>=0&&
    is_numeric($ip_array[2])&&$ip_array[2]<=255&&$ip_array[2]>=0&&is_numeric($ip_array[3])&&$ip_array[3]<=255&&$ip_array[3]>=0)
    {
      return true;
    }
    else
    {
      return false;
    }
  }

  public function ip_reverse($ip)
  {
    $ip_arr = explode('.',$ip);
    return $ip_arr[3].'.'.$ip_arr[2].'.'.$ip_arr[1].'.'.$ip_arr[0];
  }
}

?>

