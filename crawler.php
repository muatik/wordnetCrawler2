<?php
ini_set('display_errors','on');
error_reporting(E_ALL);
header('content-type:text/html;charset=utf-8');
require_once('dbConnection.php');

class wordnetCrawler{
	private $db;
	private $letters=array(
		'a','b','c','ç','d','e','f','g','ğ','h','ı','i','j','k','l','m',
		'n','o','ö','p','r','s','ş','t','u','ü','v','y','z','q','w','x'
	);
	
	public function __construct(){
		$this->db=new dbConnection();
	}
	
	private function getPage($url){
		return file_get_contents($url);
	}
	
	/*
	 * TDK büyük türkçe sözlük içinde harflerle arama yaparak kayıtlı 
	 * olan tüm sözcükleri çekmeye çalışır.
	 * örneğin: http://tdkterim.gov.tr/bts/?kategori=verilst&kelime=a%E7&ayn=dzn
	 * 
	 * Bulunan kelimeleri detay bağlantılarıyla birlikte veritabanına kaydeder
	 * */
	public function fetchWordsFromTdk($fletters=null){
		
		if($fletters==null) $fletters=$this->letters;
		
		foreach($fletters as $chr1){
			foreach($this->letters as $chr2){
				$keyword=$chr1.$chr2;
				$urlkw=urlencode(mb_convert_encoding($keyword,'ISO-8859-9','UTF-8'));
				$pNumber=0;
				
				// sonuç listesinin kaç sayfa olduğu öğreniliyor.
				$url='http://tdkterim.gov.tr/bts/?kelime='.$urlkw
				.'&kategori=verilst&ayn=dzn&konts='.($pNumber*60);
				$html=$this->getPage($url);
				$html=mb_convert_encoding($html,'UTF-8','ISO-8859-9');
				$exp='/ÖNCEKİ SAYFA<\/a> - (\d*?) sayfanın <\/span>/i';
				preg_match_all($exp,$html,$pm);
				if(isset($pm[1][0])) $pageCount=(int)$pm[1][0];
				else $pageCount=0;
				
				
				// sonuç sayfalarına tek tek gidilerek sonuçlar toplanıyor
				// en az bir sayfa olduğu varsayılıyor.
				do{
				
				$url='http://tdkterim.gov.tr/bts/?kelime='.$urlkw
				.'&kategori=verilst&ayn=dzn&konts='.($pNumber*60);
				$html=$this->getPage($url);
				$html=mb_convert_encoding($html,'UTF-8','ISO-8859-9');
				
				$exp='/<p class="thomicd">.*?<\/p>/i';
				$exp='/<a .*? href="(.*?)"><p class="thomicd">(.*?)<\/td>+/i';
				preg_match_all($exp,$html,$m);
				
				//toplanan kelimeler bu diziye dolduruluyor
				$sql='';
				$words=array();
				foreach($m[2] as $mi=>$i){
					
					$i=trim(str_replace(array('&nbsp;'),'',strip_tags($i)));
					$sql.='(\''.addslashes($i).'\',\''.$m[1][$mi].'\'),';
				}
				
				// eğer sonuç bulunmuşsa
				if($sql!=''){
					$sql=mb_substr($sql,0,-1);
					$sql='insert IGNORE into words (word,href) values '.$sql;
					$this->db->query($sql);
				}
				
				$pNumber++;
				echo 'Anahtar= '.$keyword.' Sayfa: '.$pNumber.' Bulunan='.count($m[2])."\n";
				
				
				}while($pNumber<$pageCount);
				
			}
		}
		
	}
	
	public function fetchDefinitionsFromTdk(){
		$keyword='kel başa şimşir tarak';
		$urlkw=str_replace(' ','%20',$keyword);
		$url='http://tdk.gov.tr/TR/Genel/SozBul.aspx?F6E10F8892433CFFAAF6AA849816B2EF4376734BED947CDE&Kelime='.$urlkw;
		
		$html=$this->getPage($url);
		
		//$html=file_get_contents('t1.htm');
		
		if(mb_strpos($html,'sözü bulunamadı.')!==false){
			echo 'bulunamadı'."\n";
		}
		
		// sonuçlar ayrıca işlenecek
		$exp='/<\/STRONG> ('.$keyword.')  '
		.'(<STRONG><FONT color=DarkBlue>(.*?)<\/FONT><\/STRONG>)?'
		.'<\/FONT><\/STRONG><BR><I>'
		.'<STRONG><FONT color=mediumblue>(.*?)<\/FONT><\/STRONG>'
		.'<STRONG><FONT color=mediumblue>(.*?)<\/FONT><\/STRONG><\/I><\/P> ?<P><TABLE/i';
		
		preg_match_all($exp,$html,$m);
		print_r($m);
		// şimdilik 15-20 denemede çalıştı... alternatif olarak BTS'ye bakılacak
	}
}
$a=new wordnetCrawler();
$a->fetchDefinitionsFromTdk();
?>
