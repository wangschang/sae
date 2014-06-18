<?php
/*****
20140611 监测sae服务器状态
需要开启对应的服务并初始化
需要建立表 service_status 和创建对应的存储的域
其他服务器定时监测sae状态报警

weibo @道可道
*/

function http_request_status($url,$data='')
{
	 $ch = curl_init(); 
	 curl_setopt($ch, CURLOPT_URL, $url); 
	 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
	 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	 curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
	 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	 curl_setopt($ch, CURLOPT_AUTOREFERER, 1); 
	 if($data!='')
	 curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	 $tmpInfo = curl_exec($ch); 
	 if (curl_errno($ch)) {  
		return curl_error($ch);
	 }else{
		 return curl_getinfo($ch);
		//return $tmpInfo;	 
	}	
}

$returndata = array();
//监测kv
try{
	$kv = new SaeKV();
	// 初始化KVClient对象
	$ret = $kv->init();
	// 更新key-value
	$ret = $kv->set('web_status', '1');
	// 获得key-value
	$ret = $kv->get('web_status');
	if($ret == 1)
	{
		$returndata['kv']['status'] = 1;
		$returndata['kv']['msg'] = 'succ';
	}else{
		$returndata['kv']['status'] = 0;
		$returndata['kv']['msg'] = 'kv get error!';
	}
}catch(Exception $e){
	$returndata['kv']['status'] = -1;
	$returndata['kv']['msg'] = 'kv init error!';
}
//监测mysql 读和写

try{
	$mysql = new SaeMysql();

	//先插入数据库
	$sql= 'replace into service_status set add_time=NOW() , id=1';
	//$sql = "SELECT count(*) FROM `service_status` ";
	$data = $mysql->runSql( $sql );
	if(!$data)
	{
		$returndata['mysql_insert']['status'] = -1;
		$returndata['mysql_insert']['msg'] = 'mysql insert error';
	}else{
		
		$returndata['mysql_insert']['status'] = 1;
		$returndata['mysql_insert']['msg'] = 'mysql insert succ!';
	}
	//查询数据库
	$sql = "select count(*) from service_status ";
	$data = $mysql->getData($sql);
	if($data<=0)
	{
		$returndata['mysql_select']['status'] = -1;
		$returndata['mysql_select']['msg'] = 'mysql select error!';
	}else{
		$returndata['mysql_select']['status'] = 1;
		$returndata['mysql_select']['msg'] = 'mysql insert succ!';	
	}
	
}catch( Exception $e)
{
	$returndata['mysql']['status'] = -1;
	$returndata['mysql']['msg'] = 'mysql init error!';
}
//监测memcache
try{
	$mmc=memcache_init();
	if($mmc==false)
	{
		$returndata['mc']['status'] = -1;
		$returndata['mc']['msg']  ='mc init failed';
	}
	else
	{
		memcache_set($mmc,"getstatus",1);
		if(memcache_get($mmc,"getstatus") == 1)
		{
			$returndata['mc']['status'] = 1;
			$returndata['mc']['msg']  ='mc op succ';
		}else{
			$returndata['mc']['status'] = -1;
			$returndata['mc']['msg']  ='mc op failed';	
		}
	}
}catch( Exception $e)
{
	$returndata['mc']['status'] = -1;
	$returndata['mc']['msg']  ='mc inner';
}

//监测存储是否可以写
try{
  //需要建立对应的domain
	$storage = new SaeStorage();
 	$domain = 'domain';
    $destFileName_status = 'status.txt';//需要有这个文件存在
    $destFileName = 'status1.txt';//
 	$content = '1';
 	$attr = array('encoding'=>'gzip');
 	$result = $storage->write($domain,$destFileName, $content, -1, $attr, true);
	if($result =='')
	{
		$returndata['saestorage']['status'] = -1;
		$returndata['saestorage']['msg']  ='Storage save error';
	}else{
		//获取页面  $destFileName_status
		$contents = http_request_status($result);
        //print_r($contents);
        //var_dump($contents);
        if($contents['http_code'] == '404')
		{
			$returndata['saestorage']['status'] = -1;
			$returndata['saestorage']['msg']  ='Storage get error';	
		}else{
			//判断cdn
			$cdn_url = $storage->getCDNUrl($domain,$destFileName_status);
			$contents_url = http_request_status($cdn_url);
			if($contents_url['http_code'] == '404')
			{
				$returndata['saestorage']['status'] = -1;
				$returndata['saestorage']['msg']  ='Storage get cdn error';	
			}else{
				$returndata['saestorage']['status'] = 1;
				$returndata['saestorage']['msg']  ='Storage save succ';
			}
		}
		
	}
}catch( Exception $e)
{
	$returndata['saestorage']['status'] = -1;
	$returndata['saestorage']['msg']  ='storage init error';
}

echo json_encode($returndata);





//
?>
