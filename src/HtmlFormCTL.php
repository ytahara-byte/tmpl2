<?php
/******************************************************************************************************** 
lib.tmpl2拡張ライブラリー
	GetFormIO()
	GetFieldData()
	SetFieldData($fld)
	SetAllFieldData($fld)
	SetField2Tmple($objTmpl)
	ErrorChek()
	GetPostFild())

	

	FieldDefinition  対応したいパラメーター配列
    	string $name				構造名
        $text,						入出力したい文字列
        string $mode,				入出力文字タイプ 
		        	MODE_TEXT、MODE_NUMBER、MODE_DATE、MODE_TIME
        string $postName,			HTML-FROMから取得したいPOST名
        string $templateName,		tmpl2に設定したい変数名
        int $check,					正常かのチェック 	
					CHECK_NOCHECK,CHECK_NOTEXT,CHECK_NUMZERO,CHECK_NODATE,CHECK_NONUM,CHECK_NOEMAIL,CHECK_HIRAGANA,CHECK_KANA
										(0:チェックしない,1:文字列なし,2:数字がゼロ,3:日付がない,4:数字のみ,10:メールアドレス表記ミス),11:ひらがな,12:カナ)
        string $operation,			HTMLのタイプ 
        		OP_TEXT,OP_TEXTAREA,OP_HTML,OP_SELECT,OP_CHECK,OP_RADIO
				(text:1行文字列,aria:複数行,html:複数行HTML,select:セレクトボックス,check:チェックボックス,radio:ラジオボタン)
        ?FieldOptions $options 		OP_SELECT,OP_CHECK,OP_RADIOはオプションがある
	FieldOptions 
 		bool $loop 					動的項目ありなし 0:静的 1:動的
   		array $table 				選択したい項目データ　array(1=>"a",2->"b")	

		 mode num
		  ($postName)_ZERO : 数字がゼロの時は空白になる
		 op html
		  (html)_HTML : 改行に<br />タグが追加される
		 op select
		  loopが静的時
			(html)_CODE(num) tableの配列名numに配列名が追加される
			(html)_VALUE(num) tableの配列名numに値が追加される
			(html)_SELECT(num) tableの配列名numと入出力の値が同じ時に'selected'になる
			(html)_TEXT 入出力された値に対応したtableの値
		  loopが動的時
			(html)_TEXT 入出力された値に対応したtableの値
			LIST_(html) tmpl2のloopset,next,end
			CODE tableの配列名が追加される
			NAME tableの値が追加される
			SELECT tableの配列名と入出力の値が同じ時に'selected'になる
		 op radio
		  loopが静的時
			(html)_CODE(num) tableの配列名numに配列名が追加される
			(html)_VALUE(num) tableの配列名numに値が追加される
			(html)_CHECK(num) tableの配列名numと入出力の値が同じ時に'checked'になる
			(html)_TEXT 入出力された値に対応したtableの値
		  loopが動的時
			(html)_TEXT 入出力された値に対応したtableの値
			LIST_(html) tmpl2のloopset,next,end
			CODE tableの配列名が追加される
			NAME tableの値が追加される
			CHECK tableの配列名と入出力の値が同じ時に'checked'になる
		 op check
		  loopが静的時
			(html)_CODE(num) tableの配列名numに配列名が追加される
			(html)_VALUE(num) tableの配列名numに値が追加される
			(html)_CHECK(num) tableの配列名numと入出力の値が同じ時に'checked'になる
			(html)_TEXT 入出力された値に対応したtableの値
			DEF_(html)_CODE(num) 入出力された値に対応した項目をassign_defする
		  loopが動的時
			(html)_TEXT 入出力された値に対応したtableの値
			LIST_(html) tmpl2のloopset,next,end
			LIST_(html)_CHECKED tmpl2のloopset,next,end(選択した項目のみのloop)
			CODE tableの配列名が追加される
			NAME tableの値が追加される
			TEXT tableの値が追加される(選択した時のみ)
			CHECK tableの配列名と入出力の値が同じ時に'checked'になる
*/

namespace Tmpl2;

interface FormInputInterface
{
    public function getPostData(string $name);
}
final class PostFormInput implements FormInputInterface
{
    public function getPostData(string $name):?string
    {
        return array_key_exists($name, $_POST)
            ? $_POST[$name]
            : null;
    }
}

//
// Symfony
//
final class SymfonyFormInput implements FormInputInterface
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getPostData(string $name): ?string
    {
        // 1. 指定されたキーが POST データ($_POST)に含まれているか確認
        if (!$this->request->request->has($name)) {
            return null;
        }

        $value = $this->request->request->all()[$name];

        return is_string($value) ? $value : '';
    }
}

//
// PSR-7
//
final class Psr7FormInput implements FormInputInterface
{
    private ServerRequestInterface $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getPostData(string $name): ?string
    {
        $data = $this->request->getParsedBody();

        // POSTデータ全体が配列ではない場合は null を返す
        if (!is_array($data)) {
            return null;
        }

        // 指定されたキーが存在しない場合は null を返す
        if (!array_key_exists($name, $data)) {
            return null;
        }

        $value = $data[$name];

        // 値が文字列であればそれを返し、ネストした配列などであれば空文字（または意図した値）にする
        return is_string($value) ? $value : '';
    }
}


final class FieldOptions
{
	private bool $loop;
	private array $table;
	public function __construct(
   		bool $loop = false,
   		array $table = []
   	) {
   		$this->loop = $loop;
   		$this->table = $table;
   	}
   	public function loop():bool {return ($this->loop);}
   	public function table() {return ($this->table);} 

}

final class FieldDefinition
{
// old function
	public const OP_ARIA = 'aria';
	public const OP_AREA = 'area';
//


	public const OP_TEXT 	= 'text';
	public const OP_TEXTAREA = 'textarea';
	public const OP_HTML   	= 'html';
	public const OP_SELECT 	= 'select';
	public const OP_CHECK  	= 'check';
	public const OP_RADIO  	= 'radio';


// old function
	public const MODE_TXT 	= "txt";
	public const MODE_NUM 	= "num";
//
	public const MODE_TEXT = 'text';
	public const MODE_NUMBER = 'number';
	public const MODE_DATE 	= "date";
	public const MODE_TIME 	= "time";

	public const CHECK_NOCHECK = 0;
	public const CHECK_NOTEXT = 1;
	public const CHECK_NUMZERO = 2;
	public const CHECK_NODATE = 3;
	public const CHECK_NONUM = 4;
	public const CHECK_NOEMAIL = 10;	
	public const CHECK_HIRAGANA = 11;
	public const CHECK_KANA = 12;

	private string $name;
    private $text;
    private string $mode;
    private string $postName;
    private string $templateName;
    private int $check;
    private string $operation;
    private ?FieldOptions $options;

    public function __construct(
    	string $name,
        $text,
        string $mode,
        string $postName,
        string $templateName,
        int $check,
        string $operation,
        ?FieldOptions $options = null
    ) {
    	$this->name = $name;
        $this->text = $text;
        $this->mode = $mode;
        $this->postName = $postName;
        $this->templateName = $templateName;
        $this->check = $check;
        $this->operation = $operation;
        $this->options = $options;
    }
	public function name(): string { return $this->name;}
    public function text(): ?string { return $this->text; }
    public function setText($text): void { $this->text = $text;}
    public function mode(): string {return $this->mode; }
    public function postName(): string { return $this->postName; }
    public function templateName(): string {return $this->templateName;}
    public function check():int { return $this->check; }
    public function operation(): string { return $this->operation;}
    public function options(): ?FieldOptions { return $this->options; }
}


class HtmlFormCTL {
	//private FormInputInterface $objForm;
	public FormInputInterface $objForm; 

	private ?string $basedate = '1990-01-01 00:00:00';
	/** @var FieldDefinition[] */
	private array $field = [];
    
	public function __construct($obj) 
	{
		$this->objForm = $obj;
	}
	public function GetPost($name):?string 
	{
		return ($this->objForm->getPostData($name));
	}
	public function setbasedate(?string $bd):void 
	{
		$this->basedate = $bd;
		//Default '1990-01-01 00:00:00'
		//mysql TIMESTAMP 1970-01-01
		//mysql DATE DATETIME 1000-01-01 〜 9999-12-31
	}
	public function GetDateIni() 
	{
		if (is_null($this->basedate)) {
			return (null);
		}
		list ($gdate,$gtime) = explode(" ",$this->basedate);
		list ($datey,$datem,$dated) = explode("-",$gdate);
		list ($timeh,$timei,$times) = explode(":",$gtime);
		return (mktime((int)$timeh,(int)$timei,(int)$times,(int)$datem,(int)$dated,(int)$datey));
	}

	public function GetFieldData($name):?FieldDefinition 
	{
		foreach ($this->field as $work) {
			if ($work->name() === $name) {
				return ($work);
			}
		}
		return (null);	
	}
	public function SetFieldData(FieldDefinition $fld):void 
	{
		$this->field[] = $fld;
	}
	public function SetAllFieldData(array $flds): void
	{
    	$this->field = [];

    	foreach ($flds as $fld) {
        	$this->field[] = $fld;
    	}
	}
	public function SetField2Tmple(Tmpl2 $objTmpl):Tmpl2 
	{
		if (count($this->field) == 0) {
			return ($objTmpl);
		}
		foreach ($this->field as $row) {
			switch ($row->operation()) {
			case FieldDefinition::OP_TEXT:
				switch ($row->mode()) {
				case FieldDefinition::MODE_TXT:
				case FieldDefinition::MODE_TEXT:
					$objTmpl->assign($row->templateName(),$row->text());
					break;
				case FieldDefinition::MODE_NUM:
				case FieldDefinition::MODE_NUMBER:
					$objTmpl->assign($row->templateName(),(int)$row->text());
					if ((int)$row->text() > 0) {
						$objTmpl->assign($row->templateName() . "_ZERO",$row->text());
					} else {
						$objTmpl->assign($row->templateName() . "_ZERO","");
					}
					break;
				case FieldDefinition::MODE_DATE:
					if ($row->text() != $this->GetDateIni()) {
						$objTmpl->assign($row->templateName(),date("Y/m/d",$row->text()));
					} else {
						$objTmpl->assign($row->templateName(),"");
					}
					break;
				case FieldDefinition::MODE_TIME:
					$objTmpl->assign($row->templateName(),date("H:i:s",$row->text()));
					break;
				default:
					break;
				}
				break;
			case FieldDefinition::OP_AREA:
			case FieldDefinition::OP_ARIA:
			case FieldDefinition::OP_TEXTAREA:
				$objTmpl->assign($row->templateName(),$row->text());
				$objTmpl->assign($row->templateName() . "_HTML",str_replace("\n","<br />\n",$row->text()));
				break;
			case FieldDefinition::OP_HTML:
				$objTmpl->assign($row->templateName(),$row->text());
				$objTmpl->assign($row->templateName() . "_HTML",str_replace("\n","<br />\n",$row->text()));
				break;
			case FieldDefinition::OP_SELECT:
				$objTmpl->assign($row->templateName(),$row->text());
				$objTmpl = $this->SetField2Select($objTmpl,$row);
				break;
			case FieldDefinition::OP_CHECK:
				$objTmpl->assign($row->templateName(),$row->text());
				$objTmpl = $this->SetField2Check($objTmpl,$row);
				break;
			case FieldDefinition::OP_RADIO:
				$objTmpl->assign($row->templateName(),$row->text());
				$objTmpl = $this->SetField2Radio($objTmpl,$row);
				break;
			default:
				break;
			}
		}
		return ($objTmpl);

	}
	private function SetField2Select($objTmpl,$row):Tmpl2 
	{
		$srow = $row->options();
		if ($srow->loop()) {
			$sname = "";
			$objTmpl->loopset( "LIST_" . $row->templateName());
			foreach ($srow->table() as $code => $name) {
				$objTmpl->assign("CODE",$code);
				$objTmpl->assign("NAME",$name);
				if ($row->text() == $code) {
					$objTmpl->assign("SELECT"," selected");
					$objTmpl->assign("CHECK"," selecte");

					$sname = $name;
				} else {
					$objTmpl->assign("SELECT","");
					$objTmpl->assign("CHECK","");
				}
				$objTmpl->loopnext( "LIST_" . $row->templateName());
			}
			$objTmpl->loopend( "LIST_" . $row->templateName());
			$objTmpl->assign($row->templateName() . "_TEXT",$sname);
		} else {
			foreach ($srow->table() as $code => $name) {
				$objTmpl->assign($row->templateName() . "_CODE" . $code,$code);
				$objTmpl->assign($row->templateName() . "_VALUE" . $code,$name);
				if ($row->text() == $code) {
					$objTmpl->assign($row->templateName() . "_SELECT" . $code," selected");
					$objTmpl->assign($row->templateName() . "_CHECK" . $code," selected");
					$objTmpl->assign($row->templateName() . "_TEXT",$name);
				} else {
					$objTmpl->assign($row->templateName() . "_SELECT" . $code,"");
					$objTmpl->assign($row->templateName() . "_CHECK" . $code,"");
				}
			}
		}
		return ($objTmpl);
	}
	private function SetField2Radio($objTmpl,$row):Tmpl2 
	{
		$rrow = $row->options();
		if (!$row->check()) {
			$setflg = 0;
			$startflg = 0;
			$scode = "";
			foreach ($rrow->table() as $code => $name) {
				if ($startflg == 0) {
					$scode = $code;
					$startflg = 1;
				}
				if ($row->text() === $code) {
					$setflg = 1;
					break;
				}
			}
			if (!$setflg) {
				$row->setText($scode);
			}
		}

		if ($rrow->loop()) {
			foreach ($rrow->table() as $code => $name) {
				$objTmpl->assign($row->templateName() . "_CODE" . $code,$code);
				$objTmpl->assign($row->templateName() . "_VALUE" . $code,$name);
			}
			$sname = "";
			$objTmpl->loopset( "LIST_" . $row->templateName() );

			foreach ($rrow->table() as $code => $name) {
				$objTmpl->assign("CODE",$code);
				$objTmpl->assign("NAME",$name);
				if ($row->text() == $code) {
					$objTmpl->assign("CHECK"," checked");
					$sname = $name;
				} else {
					$objTmpl->assign("CHECK","");
				}
				$objTmpl->loopnext( "LIST_" . $row->templateName());
			}
			$objTmpl->loopend( "LIST_" . $row->templateName());
			$objTmpl->assign($row->templateName() . "_TEXT",$sname);
		} else  {
			foreach ($rrow->table() as $code => $name) {
				$objTmpl->assign($row->templateName() . "_CODE" . $code,$code);
				$objTmpl->assign($row->templateName() . "_VALUE" . $code,$name);
				if ($row->text() == $code) {
					$objTmpl->assign($row->templateName() . "_CHECK" . $code," checked");
					$objTmpl->assign($row->templateName() . "_TEXT",$name);
				} else {
					$objTmpl->assign($row->templateName() . "_CHECK" . $code,"");
				}
			}
		}
		return ($objTmpl);
	}
	private function SetField2Check($objTmpl,$row):Tmpl2 
	{
		$arraymode = FALSE;
		$txt = "";
		if (is_array($row->text())) {
			$arraymode = 1;
			$txt = $this->array2string($row->text());
		} else {
			$txt = $row->text();
		}
		$objTmpl->assign($row->templateName(),$txt);
		$rrow = $row->options();
		if ($rrow->loop() && $arraymode) {
			foreach ($rrow->table() as $code => $name) {
				$objTmpl->assign($row->templateName() . "_CODE" . $code,$code);
				$objTmpl->assign($row->templateName() . "_VALUE" . $code,$name);
			}
			$objTmpl->loopset( "LIST_" . $row->templateName() );
			foreach ($rrow->table() as $code => $name) {
				$objTmpl->assign("CODE",$code);
				$objTmpl->assign("NAME",$name);
				$flg = 0;
				if (count($row->text()) > 0) {
					foreach ($row->text() as $value) {
						if ($code == $value) {
							$flg = 1;
							break;
						}
					}
				}
				if ($flg) {
					$objTmpl->assign("CHECK"," checked");
					$objTmpl->assign("TEXT",$name);
				} else {
					$objTmpl->assign("CHECK","");
					$objTmpl->assign("TEXT","");
				}
				$objTmpl->loopnext( "LIST_" . $row->templateName() );
			}
			$objTmpl->loopend( "LIST_" . $row->templateName() );

			$objTmpl->loopset( "LIST_" . $row->templateName() . "_CHECKED");
			foreach ($rrow->table() as $code => $name) {
				$flg = 0;
				if (count($row->text()) > 0) {
					foreach ($row->text() as $value) {
						if ($code == $value) {
							$flg = 1;
							break;
						}
					}
				}
				if (!$flg) {
					continue;
				}
				$objTmpl->assign("CODE",$code);
				$objTmpl->assign("NAME",$name);
				$objTmpl->assign("TEXT",$name);
				$objTmpl->loopnext( "LIST_" . $row->templateName()  . "_CHECKED");
			}
			$objTmpl->loopend( "LIST_" . $row->templateName()  . "_CHECKED");

		} else {
			foreach ($rrow->table() as $code => $name) {
				$objTmpl->assign($row->templateName() . "_CODE" . $code,$code);
				$objTmpl->assign($row->templateName() . "_VALUE" . $code,$name);
				$flg = 0;
				if (is_array($row->text())) {
					foreach ($row->text() as $value) {
						if ($code == $value) {
							$flg = 1;
							break;
						}
					}
				} else {
					if ($row->text() == $code) {
						$flg = 1;
					}
				}
				if ($flg) {
					$objTmpl->assign($row->templateName() . "_CHECK" . $code," checked");
					$objTmpl->assign($row->templateName() . "_TEXT",$name);
					$objTmpl->assign_def("DEF_" . $row->templateName() . "_CODE" . $code);
				} else {
					$objTmpl->assign($row->templateName() . "_CHECK" . $code,"");
				}
			}
		}
		return ($objTmpl);

	}

	public function ErrorChek() {
		$Errortbl = array();

		foreach ($this->field as $row) {
			if (!is_array($row->text())) {
				switch ($row->check()) {
					case FieldDefinition::CHECK_NOCHECK:
						break;
					case FieldDefinition::CHECK_NOTEXT:
						if (strlen($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						}
						break;
					case FieldDefinition::CHECK_NUMZERO:
						if ($row->text() == 0) {
							$Errortbl[] = $row->templateName();
						}
						break;
					case FieldDefinition::CHECK_NODATE:
						if ($row->text() == $this->GetDateIni()) {
							$Errortbl[] = $row->templateName();
						}
						break;
					case FieldDefinition::CHECK_NONUM:
						if (strlen($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						} else if (!preg_match("/^[0-9]+$/u", $row->text())) {
							$Errortbl[] = $row->templateName() . "2";
						}
						break;
					case FieldDefinition::CHECK_NOEMAIL:
						if (strlen($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						} else if (!preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $row->text())) {
							$Errortbl[] = $row->templateName() . "2";
						}
						break;
					case FieldDefinition::CHECK_HIRAGANA:
						if (strlen($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						} else if (!preg_match("/^[ぁ-ん 　]+$/u", $row->text())) {
							$Errortbl[] = $row->templateName() . "2";
						}
						break;
					case FieldDefinition::CHECK_KANA:
						if (strlen($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						} else if (!preg_match("/^[ァ-ヶー 　]+$/u", $row->text())) {
							$Errortbl[] = $row->templateName() . "2";
						}
						break;
					default:
						break;
				}
			} else {
				switch ($row->check()) {
					case 1:
						if (count($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						} else {
							$flg = 0;
							foreach ($row->text() as $value) {
								if (strlen($value) > 0) {
									$flg = 1;
									break;
								}
							}
							if (!$flg) {
								$Errortbl[] = $row->templateName();
							}
						}
						break;
					case 2:
						if (count($row->text()) == 0) {
							$Errortbl[] = $row->templateName();
						} else {
							$flg = 0;
							foreach ($row->text() as $value) {
								if ($value > 0) {
									$flg = 1;
									break;
								}
							}
							if (!$flg) {
								$Errortbl[] = $row->templateName();
							}
						}
						break;
					default:
						break;
				}
			}
		}
		return ($Errortbl);
	}
	public function GetPostFeld() {
		$err = 0;
		foreach ($this->field as $row) {
			$post = $row->postName();
			if (strlen($post) == 0) {
				continue;
			}
			$fi = $this->objForm->getPostData($post);
			$field = "";
			if (is_null($fi)) {
				$op = $row->operation();
				if ($op == FieldDefinition::OP_TEXT || $op == FieldDefinition::OP_TEXTAREA 
					|| $op == FieldDefinition::OP_HTML) {
					$err++;
				}
			} else {
				$field = $fi;	
			}
			$data = "";
			switch ($row->mode()) {
				case FieldDefinition::MODE_NUM:
				case FieldDefinition::MODE_NUMBER:
					$data = (int)$field;
					break;
				case FieldDefinition::MODE_TXT:
				case FieldDefinition::MODE_TEXT:
					$data = (string)$field;
					break;
				case FieldDefinition::MODE_DATE:
					$data = $this->Input2Datecnv($field);
					break;
				case FieldDefinition::MODE_TIME:
					$data = $this->Input2Timecnv($field);
					break;
			}
			if (!is_array($row->text())) {
				$row->setText($data);
			} else {
				if ($row->operation() != "check") {
					$row->setText([$data]);
				} else {
					$row->text([]);
					$data2 = $this->objForm->getPostData($row->postName());
					if (is_array($data2)) {
						$row->setText($data2);
					} else if (strlen($data2) > 0) {
						$row->setText($this->string2array($data2));
					} else {
						foreach ($row->options()->table() as $code => $value) {
							$flg = 0;
							switch ($row->mode()) {
								case FieldDefinition::MODE_NUM:
								case FieldDefinition::MODE_NUMBER:
									$data = (int)$this->objForm->getPostData($row->postName() . $code);
									if ($data > 0) {
										$flg = 1;
									}
									break;
								case FieldDefinition::MODE_TXT:
								case FieldDefinition::MODE_TEXT:
									$data = $this->objForm->getPostData($row->postName() . $code);
										if (strlen($data) > 0) {
										$flg = 1;
									}
									break;
							}
							if ($flg) {
								$work = $row->text();
								$work[] = $data;
								$row->setText($work);
							}
						}
					}
				}
			}
		}

		return ($err ? 0 : 1);
	}
	public function GetPostNOTLIST() {
		$err = [];
		foreach ($this->field as $row) {
			$post = $row->postName();
			if (strlen($post) == 0) {
				continue;
			}
			$fi = $this->objForm->getPostData($post);
			if (is_null($fi)) {
				$op = $row->operation();
				if ($op == FieldDefinition::OP_TEXT || $op == FieldDefinition::OP_TEXTAREA 
					|| $op == FieldDefinition::OP_HTML) {
					$err[] = $post;
				}
			}
		}
		return ($err);
	}
	public function MakeDatePlusTime($gdate,$gtime) {
		return (mktime((int)date("H",$gtime),(int)date("i",$gtime),(int)date("s",$gtime),
			           (int)date("m",$gdate),(int)date("d",$gdate),(int)date("Y",$gdate)));
	}

	private function Input2Datecnv($name) {
		if (strlen($name) == 0) {
			return ($this->GetDateIni());
		}
		$name = strtoupper($name);
		$name = str_replace("年","-",$name);
		$name = str_replace("月","-",$name);
		$name = str_replace("日","",$name);
 		$name = str_replace(".","-",$name);
		$name = str_replace("/","-",$name);
		$name = str_replace("－","-",$name);
		$name = str_replace("昭和","S",$name);
		$name = str_replace("平成","H",$name);
		$name = str_replace("令和","R",$name);
		$wareki = 0;
		if (strstr($name,"S") != FALSE) {
			$wareki = 1926;
			$name = str_replace("S","",$name);
		}
		if (strstr($name,"H") != FALSE) {
			$wareki = 1988;
			$name = str_replace("H","",$name);
		}
		if (strstr($name,"R") != FALSE) {
			$wareki = 2018;
			$name = str_replace("R","",$name);
		}
		$year = 0; $month = 0; $day = 0;
		if (strstr($name,"-") != FALSE) {
			$ding = explode("-",$name);
			if(count($ding) == 2) {
				$month = (int)$ding[0];	$day = (int)$ding[1];
			} else if (count($ding) == 3) {
				$year = (int)$ding[0]; $month = (int)$ding[1];	$day = (int)$ding[2];
			}
		} else {
			$name2 = substr("00000000" . $name,-8);
			$year = (int)substr($name2,0,4);
			$month = (int)substr($name2,4,2);
			$day = (int)substr($name2,6,2);
		}
		if ($wareki > 0) {
			$year += $wareki;
		} 
		if ($year == 0) {
			$year = date("Y");
		}
		if ($year < 40) {
			$year += 2000;
		} else if ($year < 100) {
			$year += 1900;
		}
		if($month == 0) {
			$month = date("m");
		}
		if ($year == 0 || $month == 0 || $day == 0) {
			return ($this->GetDateIni());
		}

		return (mktime(0,0,0,$month,$day,$year));

	}
	private function Input2Timecnv($name) {
		if (strlen($name) == 0) {
			return ($this->GetDateIni());
		}
		$name = strtoupper($name);
		$name = str_replace(".",":",$name);
		$name = str_replace("/",":",$name);
		$name = str_replace("-",":",$name);
		$name = str_replace("－",":",$name);
		$timeh = 0; $timei = 0; $times = 0;
		if (strstr($name,":") != FALSE) {
			$ding = explode(":",$name);
			if(count($ding) == 2) {
				$timei = (int)$ding[0];	$times = (int)$ding[1];
			} else if (count($ding) == 3) {
				$timeh = (int)$ding[0]; $timei = (int)$ding[1];	$times = (int)$ding[2];
			}
		} else {
			$name2 = substr("000000" . $name,-6);
			$timeh = (int)substr($name2,0,2);
			$timei = (int)substr($name2,2,2);
			$times = (int)substr($name2,4,2);
		}
		if ($timeh == 0) {
			$timeh = date("H");
		}
		if($timei == 0) {
			$timei = date("i");
		}

		return (mktime($timeh,$timei,$times,date("m"),date("d"),date("Y")));

	}


	private function string2array($buff,$max = 0) {
		$tbl = array();
		if ($max > 0) {
			for ($idx = 0;$idx < $max;$idx++) {
				$tbl[$idx] = 0;
			}
		}
		if (strlen($buff) == 0) {
			return ($tbl);
		}
		for ($idx = 0;$idx < strlen($buff);$idx++) {
			if (substr($buff,$idx,1) == "0") {
				$tbl[$idx] = 0;
			} else {
				$tbl[$idx] = $idx + 1;
			}
		}
		return ($tbl);
	}
	private function array2string($tbl,$max = 0) {
		$buff = "";
		if (count($tbl) == 0) {
			if ($max == 0) {
				return ($buff);
			}
			for ($idx = 0;$idx < $max;$idx++) {
				$buff .= "0";
			}
			return ($buff);
		}
		foreach ($tbl as $code) {
			if ($max < $code) {
				$max = $code;
			}
		}
		for ($idx = 0;$idx < $max;$idx++) {
			$flg = 0;
			$icode = $idx + 1;
			foreach ($tbl as $code) {
				if ($code == $icode) {
					$flg = 1;
					break;
				}
			}
			if ($flg == 0) {
				$buff .= "0";
			} else {
				$buff .= "1";
			}
		}
		return ($buff);

	}
}
?>