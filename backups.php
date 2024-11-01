<?php
function wpstatusSanitizeData($data)
{
  return mysql_real_escape_string($data);
}

function wpstatusDatabaseBackup()
{
  $host = DB_HOST;
  $user = DB_USER;
  $pass = DB_PASSWORD;
  $name = DB_NAME;
  $tables = '*';
  $link = mysql_connect($host,$user,$pass);
  mysql_select_db($name,$link);
  
  //get all of the tables
  if($tables == '*')
  {
    $tables = array();
    $result = mysql_query('SHOW TABLES');
    while($row = mysql_fetch_row($result))
    {
      $tables[] = $row[0];
    }
  }
  else
  {
    $tables = is_array($tables) ? $tables : explode(',',$tables);
  }
  $return = '';
  
  //cycle through
  $tmp_fp = @fopen(WPSTATUS_WRITABLE_FOLDER.'/database-backup.sql', 'w');

  if(!$tmp_fp) return false;

  foreach($tables as $table)
  {
    $result = mysql_query('SELECT * FROM '.$table);
    $num_fields = mysql_num_fields($result);
    
    //$return.= 'DROP TABLE IF EXISTS'.$table.';';
    $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
    $return .= "\n\n".$row2[1].";\n\n";
    fwrite($tmp_fp, $return);
    $return = '';
    for ($i = 0; $i < $num_fields; $i++) 
    {
      while($row = mysql_fetch_row($result))
      {
        $return .= 'INSERT INTO `'.$table.'` VALUES(';
        for($j=0; $j<$num_fields; $j++) 
        {
          if (!empty($row[$j])) 
          { 
            $return.= "'".wpstatusSanitizeData($row[$j])."'" ; 
          } 
          else 
          { 
            $return.= "''"; 
          }

          if ($j<($num_fields-1)) 
          { 
            $return.= ','; 
          }
        }
        $return .= ");";
        fwrite($tmp_fp, $return."\n");
        flush();
        $return = '';
      }
    }
  }
  
  fclose($tmp_fp);

  return true;
}

function wpstatusGetFiles($source)
{
  $files = glob_recursive($source.'/{,.}*', GLOB_BRACE);
  foreach($files as $key => $f)
  {
    $files[$key] = '/'.ltrim(str_replace(ABSPATH, '', $f), '/');
    if(is_dir($f) || array_pop(explode('/', $f)) == '.' || array_pop(explode('/', $f)) == '..')
    {
      unset($files[$key]);
    }
  }
  return $files;
}

function wpstatusBackup($filetime)
{ 
  set_time_limit(0);
  if(!wpstatusDatabaseBackup($ftpconn))
  {
    echo json_encode(array('status' => 0, 'message' => 'Could not backup database'));
    exit(0);
  }
  
  $changed_list = @fopen(WPSTATUS_WRITABLE_FOLDER.'/.MANIFEST', 'w');
  $full_list = @fopen(WPSTATUS_WRITABLE_FOLDER.'/.FULLLIST', 'w');

  if(!$changed_list || !$full_list)
  {
    echo json_encode(array('status' => 0, 'message' => 'Could not write change manifest'));
    exit(0);
  }
  else
  {
    $files = wpstatusGetFiles(ABSPATH);
    list($temp_hh, $temp_mm) = explode(':', date('P'));
    $gmt_offset_server = $temp_hh + $temp_mm / 60;

    foreach($files as $f)
    {
      $f = ltrim($f, '/');
      fwrite($full_list, ltrim($f, '/')."\n");
      if(@filemtime($f) - ($gmt_offset_server * 3600) > $filetime)
      {
        fwrite($changed_list, ltrim($f, '/')."\n");
      }
    }
    fclose($changed_list);
    fclose($full_list);

    echo json_encode(array('status' => 1));
    exit(0);
  }
}

function glob_recursive($pattern, $flags = 0)
{
  $files = glob($pattern, $flags);
 
  $dirs = glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT);

  if(!empty($dirs))
  {
    foreach ($dirs as $dir)
    {
      $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
    }
  }
 
  return $files;
}

function wpstatusSupportsBackups() 
{
  return true;
}

function wpstatus_ftp_mkdir(&$conn, $dir)
{
  if(!@ftp_mkdir($conn, $dir) && $dir != '/')
  {
    wpstatus_ftp_mkdir($conn, dirname($dir));
    ftp_mkdir($conn, $dir);
  }
}