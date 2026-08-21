<?php
define( "TMPL_FILE_DIR", __DIR__ . '/Templates/');

if (!defined('DEF_TMPL2_PATH')) {
define( "DEF_TMPL2_PATH" , __DIR__ . '/../src/');
//define( "DEF_TMPL2_PATH" , './');
};

require_once(DEF_TMPL2_PATH  . 'tmpl2.php');
require_once(DEF_TMPL2_PATH  . 'HtmlFormCTL.php');
use Tmpl2\Tmpl2;
use Tmpl2\HtmlFormCTL;
use Tmpl2\PostFormInput;
//use Tmpl2\SymfonyFormInput; //Symfony
//use Tmpl2\Psr7FormInput;    //PSR-7

use Tmpl2\FieldDefinition;
use Tmpl2\FieldOptions;



class DataIO 
{
    private $data = [];
    public function __construct() 
    {
        $this->data = $this->DataClear();
    }
    private function DataClear() {
        $data = ['id'=>'','name'=>'','zip'=>'','pref'=>0,'add1'=>'','add2'=>'','sex'=>0,'email'=>'','tel'=>'','check'=>false];
        return ($data);
    } 
    public function getData($key):void
    {

        //Data Read ....

        if ($key == 'a001') {
            $data = ['id'=>'a001','name'=>'山田 花子','zip'=>'123-4567','pref'=>13,'add1'=>'千代田区XXX-XXX','add2'=>'1-234-56','sex'=>1,'email'=>'hoge@hoge.com','tel'=>'03-1234-5678','check'=>true];
        } else {
            $data = $this->DataClear();
        }
        $this->data = $data;
    
    }
    public function putData():void
    {
        //Data Read ....
    
    }
    public function get():array 
    {
        return ($this->data);
    }
    public function put($name,$value):void 
    {
        $this->data[$name] = $value;
    }
} 



class memberctl 
{
    private HtmlFormCTL $formcrl;

    private ?string $keyword = null;
    private $prefs = [0=>'',
    			1=>'北海道', 2=>'青森県',  3=>'岩手県',  4=>'宮城県',   5=>'秋田県', 6=>'山形県',
				7=>'福島県', 8=>'茨城県',  9=>'栃木県', 10=>'群馬県',  11=>'埼玉県',12=>'千葉県',
			   13=>'東京都',14=>'神奈川県',15=>'新潟県',16=>'富山県',  17=>'石川県',18=>'福井県',
			   19=>'山梨県',20=>'長野県', 21=>'岐阜県', 22=>'静岡県',  23=>'愛知県',24=>'三重県',
			   25=>'滋賀県',26=>'京都府', 27=>'大阪府', 28=>'兵庫県',  29=>'奈良県',30=>'和歌山県',
			   31=>'鳥取県',32=>'島根県', 33=>'岡山県', 34=>'広島県',  35=>'山口県',36=>'徳島県',
			   37=>'香川県',38=>'愛媛県', 39=>'高知県', 40=>'福岡県',  41=>'佐賀県',42=>'長崎県',
			   43=>'熊本県',44=>'大分県', 45=>'宮崎県', 46=>'鹿児島県',47=>'沖縄県'];

    public function __construct() 
    {
        $this->formcrl = new HtmlFormCTL(new PostFormInput());
    }
    public function GetPost($name):?string
    {
        return($this->formcrl->GetPost($name));
    }
	public function EditDisplay($errortbl = []) 
    {
		$objTmpl = new Tmpl2(TMPL_FILE_DIR . 'sample04/entry.html');
        $objTmpl->assign('TITLE','Sample-04');
		$objTmpl = $this->formcrl->SetField2Tmple($objTmpl);
		if (count($errortbl) > 0) {
			foreach ($errortbl as $errormsg) {
				$objTmpl->assign_def("DEF_ERROR_" . $errormsg);
			}
		}
        echo $objTmpl->render();
    }
	public function ConfDisplay() 
    {
		$objTmpl = new Tmpl2(TMPL_FILE_DIR . 'sample04/conf.html');
        $objTmpl->assign('TITLE','Sample-04');
		$objTmpl = $this->formcrl->SetField2Tmple($objTmpl);
        echo $objTmpl->render();
    }
	public function ViewDisplay() 
    {
		$objTmpl = new Tmpl2(TMPL_FILE_DIR . 'sample04/view.html');
        $objTmpl->assign('TITLE','Sample-04');
		$objTmpl = $this->formcrl->SetField2Tmple($objTmpl);
        echo $objTmpl->render();
    }
	public function RegistDisplay() 
    {
		$objTmpl = new Tmpl2(TMPL_FILE_DIR . 'sample04/regist.html');
        $objTmpl->assign('TITLE','Sample-04');
		$objTmpl = $this->formcrl->SetField2Tmple($objTmpl);
        echo $objTmpl->render();
    }
    public function Notfound() 
    {
        $objTmpl = new Tmpl2(TMPL_FILE_DIR . 'sample04/error.html');
        $objTmpl->assign('TITLE','Sample-04');
        $objTmpl = $this->formcrl->SetField2Tmple($objTmpl);
        header("HTTP/1.1 404 Not Found");
        echo $objTmpl->render();
    }
    public function GetPostFeld():bool 
    {
		$flg = $this->formcrl->GetPostFeld();

        $tmp = (int)$this->formcrl->GetFieldData('ZIP1')->text();
        if ($tmp > 0) {
            $this->formcrl->GetFieldData('ZIP1')->setText(substr("000" . $tmp,-3));
        }
        $tmp = (int)$this->formcrl->GetFieldData('ZIP2')->text();
        if ($tmp > 0) {
            $this->formcrl->GetFieldData('ZIP2')->setText(substr("0000" . $tmp,-4));
        }
        return ($flg);

	}
	public function ErrorChek() 
    {
		$Errortbl = $this->formcrl->ErrorChek();
        $Errortbl = $this->ErrorChekAdd($Errortbl);
        return ($Errortbl);
	}
	public function ErrorChekAdd($Errortbl) 
    {
        $tmp = (int)$this->formcrl->GetFieldData('ZIP1')->text();
        if ($tmp == 0) {
            $Errortbl[] = "ZIP";
        }
        return ($Errortbl);
    }
    //mode 0:Update / 1:Delete
    public function DataEntry($mode=0):bool 
    {
        if ($mode) {
//            $this->SetKeyword(null);
//            $row = $this->InitPostData();
            $this->formcrl->GetFieldData('NAME')->setText('');
            $this->formcrl->GetFieldData('ZIP1')->setText('');
            $this->formcrl->GetFieldData('PREF')->setText(0);
            $this->formcrl->GetFieldData('ADD1')->setText('');
            $this->formcrl->GetFieldData('ADD2')->setText('');
            $this->formcrl->GetFieldData('SEX')->setText(0);
            $this->formcrl->GetFieldData('EMAIL')->setText('');
            $this->formcrl->GetFieldData('TEL')->setText('');
            $this->formcrl->GetFieldData('CHECK')->setText(false);
            return(false);
        }
        $row = new DataIO();
        $row->put('id',$this->formcrl->GetFieldData('ID')->text());
        $row->put('name',$this->formcrl->GetFieldData('NAME')->text());
        $row->put('zip',$this->formcrl->GetFieldData('ZIP1')->text() . '-' . $this->formcrl->GetFieldData('ZIP2')->text());
        $row->put('pref',$this->formcrl->GetFieldData('PREF')->text());
        $row->put('add1',$this->formcrl->GetFieldData('ADD1')->text());
        $row->put('add2',$this->formcrl->GetFieldData('ADD2')->text());
        $row->put('sex',$this->formcrl->GetFieldData('SEX')->text());
        $row->put('email',$this->formcrl->GetFieldData('EMAIL')->text());
        $row->put('tel',$this->formcrl->GetFieldData('TEL')->text());
        $row->put('check',$this->formcrl->GetFieldData('CHECK')->text());
        return (TRUE);
    }
    public function InitFiledParamData():void 
    {
        $row = $this->InitPostData();
        $FLD = [];

        $FLD[] = new FieldDefinition(
            'ID'  ,$row['id']  ,FieldDefinition::MODE_TEXT,'id' ,'ID'  ,FieldDefinition::CHECK_NOCHECK,FieldDefinition::OP_TEXT);

        $FLD[] = new FieldDefinition(
            'NAME',$row['name'],FieldDefinition::MODE_TEXT,'name','NAME',FieldDefinition::CHECK_NOTEXT,FieldDefinition::OP_TEXT);
        
        $zip1 = '';
        $zip2 = '';
        if (strlen($row['zip']) > 0) {
            list($zip1,$zip2) = explode('-',$row['zip']);
        }
        $FLD[] = new FieldDefinition(
            'ZIP1',$zip1      ,FieldDefinition::MODE_TEXT,'zip1','ZIP1',FieldDefinition::CHECK_NONUM,FieldDefinition::OP_TEXT);
        $FLD[] = new FieldDefinition(
            'ZIP2',$zip2      ,FieldDefinition::MODE_TEXT,'zip2','ZIP2',FieldDefinition::CHECK_NONUM,FieldDefinition::OP_TEXT);
        $FLD[] = new FieldDefinition(
            'PREF',$row['pref'],FieldDefinition::MODE_NUMBER,'pref','PREF',FieldDefinition::CHECK_NUMZERO,FieldDefinition::OP_SELECT,
            new FieldOptions(TRUE,$this->prefs));
        $FLD[] = new FieldDefinition(
            'ADD1',$row['add1'],FieldDefinition::MODE_TEXT,'add1','ADD1',FieldDefinition::CHECK_NOTEXT,FieldDefinition::OP_TEXT);
        $FLD[] = new FieldDefinition(
            'ADD2',$row['add2'],FieldDefinition::MODE_TEXT,'add2','ADD2',FieldDefinition::CHECK_NOCHECK,FieldDefinition::OP_TEXT);
        $FLD[] = new FieldDefinition(
            'SEX',$row['sex'],FieldDefinition::MODE_NUMBER,'sex','SEX',FieldDefinition::CHECK_NUMZERO,FieldDefinition::OP_RADIO,
            new FieldOptions(TRUE,[1=>'男性',2=>'女性',3=>'無回答']));
        $FLD[] = new FieldDefinition(
            'EMAIL',$row['email'],FieldDefinition::MODE_TEXT,'email','EMAIL',FieldDefinition::CHECK_NOEMAIL,FieldDefinition::OP_TEXT);
        $FLD[] = new FieldDefinition(
            'TEL',$row['tel'],FieldDefinition::MODE_TEXT,'tel','TEL',FieldDefinition::CHECK_NOCHECK,FieldDefinition::OP_TEXT);


        $FLD[] = new FieldDefinition(
            'CHECK',false,FieldDefinition::MODE_NUMBER,'regi','REGI',FieldDefinition::CHECK_NUMZERO,FieldDefinition::OP_CHECK,
            new FieldOptions(FALSE,[0=>"disable",1=>'enable']));


        $this->formcrl->SetAllFieldData($FLD);
    }
    public function SetKeyword(?string $key):void
    {
        $this->keyword = $key;
    }
    public function InitPostData():array 
    {
        $IOClass = new DataIO();
        if (!is_null($this->keyword))
        {
            $IOClass->getData($this->keyword);
        }
        return ($IOClass->get());
    }
}
?>