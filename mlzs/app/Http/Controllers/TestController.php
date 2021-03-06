<?php
namespace App\Http\Controllers;
use App\Models\Article;
use App\Models\Account;
use App\Models\ArticleTime;
use App\Models\BizArticle;
use DB;
use Auth;
use Session;
class TestController extends Controller{

	public function index(){
		$newList = DB::select( DB::raw("SELECT FROM_UNIXTIME(t.lastModified) as mindt,content_url as url from t_article as t   where  FROM_UNIXTIME(t.lastModified)<(now() - INTERVAL 24 HOUR) ORDER BY t.lastModified desc  "));

		$start = strtotime(date('Y-m-d'));
		$interval = '+30 minutes';
		$endofday = strtotime('+1 day',$start);
		$endtime = strtotime('+1 day',$start);
		$index = $start;
		$temp = array();
		$result = array();
		while($index <$endtime){
			$result[] = array('gt'=>date('H:i',$index),'lt'=>date('H:i',strtotime($interval,$index)),'count'=>0,'readnum'=>0);
			$index = strtotime($interval,$index);
		} 
		unset($result[count($result)-1]);
		$result[] = array('gt'=>'23:30','lt'=>'23:59','count'=>0,'readnum'=>0);
		$result2 = $result ;
		$result3= $result ;
		echo "<pre>";
		foreach($newList as $k=>$v){
			$pubtime = date('H:i',strtotime($v->mindt)); 
			$starttime = $v->mindt;
			$endtime  = date('Y-m-d H:i:s',strtotime('+1 day',strtotime($v->mindt)));
			#print_r($starttime);
			#print_r($endtime);
			#exit;
			foreach($result as $k1=>&$v1){
				if($pubtime>=$v1['gt']&&$pubtime<$v1['lt']){
					$v1['count']++;	
					$readnum = 0 ;
					$article=DB::select( DB::raw("SELECT  datetime as datetime,readnum,likenum from t_article_time where url='{$v->url}' and datetime>='$endtime' order by datetime asc limit 1"));
					if(count($article)>0){
						$readnum = $article[0]->readnum;	
					}
					if($readnum==0){
						$article=DB::select( DB::raw("SELECT max(readnum) as readnum from t_article_time where url='{$v->url}' and datetime<='$endtime'  limit 1"));
						if(count($article)>0){
							$readnum = $article[0]->readnum;	
						}

					}
				
					$v1['readnum'] += $readnum< 0 ? 0:$readnum;
					#echo $v1['readnum']."\n";
					#echo $v1['count']."\n";



				}
			}

		}
		#计算0-24小时每半小时发文数量
		$file ='/var/www/html/mlzs/public/upload/result.txt'; 
		$header = array();
		foreach($result as $k=>$v){
			$header[] = $v['gt'];	
		}
		$th = "时间\t".implode("\t",$header)."\n";
		echo $th;
		$count ="发文数量\t";
		$readnum = "平均阅读数\t";
		foreach($result as $k=>$v){
			#print_r($v);
			$count.=$v['count']."\t";
			if($v['count']!=0){
				$meanreadnum = $v['readnum']/$v['count'];
				$readnum .= $meanreadnum."\t";
			}else{
				$readnum.="0\t";	
			}
		}
		$count.="\n";
		$readnum.="\n";
		$meanreadnum=$readnum;
		echo $count;
		echo $meanreadnum;
		#file_put_contents($file, $th.$count.$readnum, FILE_APPEND | LOCK_EX);

		#exit;
	//	exit;
		foreach($newList as $k=>$v){
			$url = $v->url;
			$publishtime = $v->mindt;
			$createtime = date('Y-m-d H:i:s',strtotime($publishtime));
			$endtime =  min(strtotime("+1 day", strtotime($publishtime)), time() ) ;
			$enddate = date('Y-m-d H:i:s',$endtime);
			$interval = '+30 minutes';
			$data = array();
			$index = strtotime($publishtime);
			while($index <=$endtime){
				$data[] = array('datetime'=>date('Y-m-d H:i:s',$index),'time'=>date('Y-m-d H:i:s',$index),'readnum'=>0,'likenum'=>0); 	
				$index = strtotime($interval,$index);
			}
			#print_r($createtime);
			#$exit;
			#print_r($createtime );
			$statis = DB::select(DB::raw("SELECT  datetime as datetime,readnum,likenum from t_article_time where url='{$v->url}' and datetime>='$createtime' and datetime<='{$enddate}' order by datetime asc"));
			if(count($statis)>0){
				foreach($statis as $st){
					for($i=0;$i<count($data);$i++){
						if($i<count($data)-1){
							if($data[$i]['datetime']<=$st->datetime && $data[$i+1]['datetime']>$st->datetime){
								$data[$i]['readnum']= $st->readnum >0 ? $st->readnum : 0;
								$data[$i]['likenum']= $st->likenum >0 ? $st->likenum :0;
								break;	
							}
						}	
						if($i==count($data)-1 && $st->datetime>=$data[$i]['datetime']){
							$data[$i]['readnum']= $st->readnum >0 ? $st->readnum:0;
							$data[$i]['likenum']= $st->likenum >0 ? $st->likenum:0;
							break;	

						}
					}
				}	
			}
			for($i=1;$i<count($data);$i++){
				if($data[$i-1]['readnum']>$data[$i]['readnum']){
					$data[$i]['readnum'] = $data[$i-1]['readnum'];	
				}
				if($data[$i-1]['likenum']>$data[$i]['likenum']){
					$data[$i]['likenum'] = $data[$i-1]['likenum'];	
				}

			}

			$article_incr = array();
			$article_incr[] = array('likenum'=>$data[0]['likenum'],'readnum'=>$data[0]['readnum']);
			for($i=1;$i<count($data);$i++){
				$temp  = array();
				$temp['likenum'] = $data[$i]['likenum'] - $data[$i-1]['likenum'];
				$temp['readnum'] = $data[$i]['readnum'] - $data[$i-1]['readnum'];
				$article_incr[]  =$temp; 

			}
			foreach($result2  as $k2=>&$v2){
				if(!array_key_exists('readnum',$v2)){
					$v2['readnum'] = 0 ;	
				}
				if(!array_key_exists('likenum',$v2)){
					$v2['likenum'] = 0 ;	
				}
				$v2['likenum'] += $article_incr[$k2]['likenum'];
				$v2['readnum'] += $article_incr[$k2]['readnum'];

			}

		}
		$likenum ="点赞数\t";
		$readnum ="阅读数\t";
		foreach($result2 as $k=>$v){
			$likenum.=$v['likenum']."\t";	
			$readnum.=$v['readnum']."\t";
		}
		$likenum.="\n";
		$readnum.="\n";
		echo $likenum;
		echo $readnum;
		file_put_contents($file, $th.$count.$meanreadnum.$likenum.$readnum, FILE_APPEND | LOCK_EX);
		exit;


		exit;

	}

}
