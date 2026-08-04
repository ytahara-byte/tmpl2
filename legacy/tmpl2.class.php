<?php
// テンプレートクラス
// 
// PHP4.1.x対応のテンプレートクラスです。
// 川里氏の作成したテンプレートクラスを大幅に拡張しています。
//
// ファイル中の%[_\-a-zA-Z][_\-\.0-9a-zA-Z]*%を指定した文字列に
// 置換を行います。
// 
// <!-- tmpl:loop %XXX% --><!-- tmpl:endloop %XXX% -->
//   ループにも対応しています(ループのネストは可)。
// 
// <!-- tmpl:def %XXX% -->
//   変数の定義をHTML上で指定可能。
//
// <!-- tmpl:ifdef %XXX% --><!-- tmpl:else --><!-- tmpl:endif -->
//   変数の定義があったときだけ出力する処理も可能。
// 
// <!-- tmpl:ifndef %XXX% --><!-- tmpl:else --><!-- tmpl:endif -->
//   変数の定義がないときだけ出力する処理も可能。
// 
// <!-- tmpl:ext2php %html% --><a href="foo.html">foo</a>
//   アンカーを html (htm) から php に変更。
//
// テンプレートの1行のMAXは16KBまで。
//
// BASE -- Id: tmpl2.class.inc,v 1.9 2001/12/24 14:32:08 kawa Exp --
// $Id: tmpl2.class.php,v 1.18 2002/10/20 07:53:53 satou Exp $
//
// error_reporting(E_ALL); //未設定変数の警告表示

define ("UNDEF2BLANK", FALSE);  // 値が assign されていない場合、TRUE：消去、FALSE：そのまま（未チェック）

class Tmpl2 {
	var $fname    ; // テンプレートファイル名
	var $kconv    ; // 漢字変換出力
	var $kcin     ; // 入力ファイル漢字コード
	var $kcout    ; // 出力漢字コード
	var $dbg      ; // デバッグモード

	var $stopflag ; // 動作停止フラグ
	var $runloop  ; // 全処理回数
	var $arDefStack      ;  // ifdef / ifndef ネストスタック
	var $arPhpLoopStack  ;  // PHP側 loop ネストスタック
	var $arHtmlLoopStack ;  // HTML側 loop ネストスタック
	var $arDefList       ;  // ifdef / ifndef リスト
	var $arPhpLoopList   ;  // PHP側 loop リスト
	var $arHtmlLoopList  ;  // HTML側 loop リスト
	var $arDefValue      ;  // ifdef / ifndef 展開用リスト
	var $arChangeValue   ;  // 単純置換用リスト
	var $arLoopValue     ;  // loop 中の置換用リスト
	var $arHtmlTemp      ;  // テンプレートHTMLソースの解析
	var $arHtmlTemp2     ;  // ifdef 処理後のテンプレートHTML
	var $arOneLoopValue  ;  // loop １回の変数

	// コンストラクタ
	function Tmpl2( $filename = "./template.htm" ,
				$icode = "" , $ocode = "SJIS" ) {
		$this->fname	= $filename ;
		$this->dbg      = 0;
		$this->runloop  = array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$this->stopflag = false;
		$this->arOneLoopValue = array() ;
		if( $icode == "" ) { 
			$this->kconv = 0 ;
			$this->kcin  = "EUC-JP" ;
			$this->kcout = "SJIS" ;
		} else {
			$this->kconv = 1 ;
			$this->kcin  = $icode ;
			$this->kcout = $ocode ;
		}
	}

	// デバッグモードフラグ設定
	// -1:処理時間計測、ループ回数報告
	//  0:通常モード
	//  1:配列のみ表示
	//  2:全ての情報を表示
	function dbgmode( $dbgflag ) {
		$this->dbg = $dbgflag ;
		if ( $dbgflag == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 00 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
		}
	}

	// 漢字の変換設定
	// $conv 0:変換しない   1:変換する 
	// $incode , $outcode : "EUC-JP" , "SJIS" , "JIS" 
	function set_kcode( $conv , $incode = "EUC-JP" , $outcode = "SJIS" ) {
		if ( $conv ) {
			$this->kconv = 1 ;
			$this->kcin  = $incode ;
			$this->kcout = $outcode ;
		} else {
			$this->kconv = 0 ;
		}
	}

	// テンプレートファイル名の変更
	function set_fname( $filename ) {
		if ( ! file_exists ( $filename ) ) {
			$this->stopflag = true ;
			echo "<b>php source error: set_fname() file not open.</b><br>\n";
			return ;
		}
		$this->fname = $filename ;
	}

	// 置換用変数の追加
	function assign( $name , $value ) {
		if( isset( $this->arPhpLoopStack ) )
			$nested = count( $this->arPhpLoopStack ) ;
		else
			$nested = 0;

		if ( $nested == 0 ) {
			$this->arChangeValue[ "%" . $name . "%" ] = $value ; 
		}
		else {
			$this->arOneLoopValue[ $nested - 1 ][ "%" . $name . "%" ] = $value ;
		}

		if ( $this->dbg > 1 ) {
			echo "<!-- assign [%" . $name . "%] = \"" . $value . "\" -->\n" ;
		}
	}

	// ifdef / ifndef 展開用変数の追加
	function assign_def( $name ) {
		$this->arDefValue[ "%" . $name . "%" ] = true ; 
		if ( $this->dbg > 1 ) {
			echo "<!-- assign_def [%" . $name . "%] = \"true\" -->\n" ;
		}
	}

	// ループ名設定
	function loopset( $lpname ) {
		if ( $lpname == "" ) {
			// loop 名が無い
			$this->stopflag = true ;
			echo "<b>php source error: loopset() no name.</b><br>\n" ;
			return ;
		}

		if( isset( $this->arPhpLoopStack ) )
			$nested = count( $this->arPhpLoopStack ) ;
		else
			$nested = 0;

		if( $nested > 0 ) {
			// loop がネストしている
			foreach ( $this->arPhpLoopStack as $work ) {
				if ( $work[ "lop_self" ] == $lpname ) {
					// ネスト中の loop 名が重複
					$this->stopflag = true ;
					echo "<b>php source error: loopset( " . $lpname . " ) conflict name.</b><br>\n" ;
					return ;
				}
			}
		}

		// 正しいループと判断
		if( isset( $this->arPhpLoopList ) )
			$php_list_count = count( $this->arPhpLoopList ) ;
		else
			$php_list_count = 0;
		$this->arPhpLoopStack[ $nested ] = array (
			"lop_self"   => $lpname ,
			"lop_cnt"    => 1 , 
			"lop_list"   => $php_list_count
		);

		if( $nested == 0 ) {
			$this->arPhpLoopList[] = array (
				"lop_root"   => $this->arPhpLoopStack[ 0 ][ "lop_self" ] ,
				"lop_parent" => $this->arPhpLoopStack[ $nested ][ "lop_self" ] ,
				"lop_pcnt"   => $this->arPhpLoopStack[ $nested ][ "lop_cnt" ] ,
				"lop_self"   => $lpname ,
				"lop_nest"   => $nested + 1 ,
				"lop_allcnt" =>  0   // ループ開始時は 0 を代入
			);
		}
		else {
			$this->arPhpLoopList[] = array (
				"lop_root"   => $this->arPhpLoopStack[ 0 ][ "lop_self" ] ,
				"lop_parent" => $this->arPhpLoopStack[ $nested - 1 ][ "lop_self" ] ,
				"lop_pcnt"   => $this->arPhpLoopStack[ $nested - 1 ][ "lop_cnt" ] ,
				"lop_self"   => $lpname ,
				"lop_nest"   => $nested + 1 ,
				"lop_allcnt" =>  0   // ループ開始時は 0 を代入
			);
		}

		if ( $this->dbg > 1 ) {
			echo "<!-- loopset [" . $lpname . "] -->\n" ;
		}
	}

	// ループの繰り返し
	function loopnext( $lpname ) {
		if ( $lpname == "" ) {
			// loop 名が無い
			$this->stopflag = true ;
			echo "<b>php source error: loopset() no name.</b><br>\n" ;
			return ;
		}

		if( isset( $this->arPhpLoopStack ) )
			$nested = count( $this->arPhpLoopStack ) ;
		else
			$nested = 0;

		if ( $nested < 1 ) {
			// loop スタックが無い
			$this->stopflag = true ;
			echo "<b>php source error: loopnext( " . $lpname . " ) no stack.</b><br>\n" ;
			return ;
		}
		if ( $this->arPhpLoopStack[ $nested - 1 ][ "lop_self" ] != $lpname ) {
			// 対象 loop 名が loop スタックと一致しない
			$this->stopflag = true ;
			echo "<b>php source error: loopnext( " . $lpname . " ) last stack name false.</b><br>\n" ;
			return ;
		}

		// 正しいループと判断
		$lop_list = $this->arPhpLoopStack[ $nested - 1 ][ "lop_list" ] ;
		$lop_cnt  = $this->arPhpLoopStack[ $nested - 1 ][ "lop_cnt" ] ;
		if( isset( $this->arOneLoopValue[ $nested - 1] ) ){
			$this->arLoopValue[] = array (
				"lop_list" => $lop_list ,
				"lop_cnt"  => $lop_cnt ,
				"value"    => $this->arOneLoopValue[ $nested - 1]
			) ;
			unset( $this->arOneLoopValue[ $nested - 1] ) ;
		}
		else{
			$this->arLoopValue[] = array (
				"lop_list" => $lop_list ,
				"lop_cnt"  => $lop_cnt ,
				"value"    => NULL
			) ;
		}

		$this->arPhpLoopStack[ $nested - 1 ][ "lop_cnt" ] ++ ;
		$this->arPhpLoopList[ $lop_list ][ "lop_allcnt" ] ++ ;

		if ( $this->dbg > 1 ) {
			echo "<!-- loopnext [" . $lpname . "] -->\n" ;
		}
	}

	// ループ終了
	function loopend( $lpname ) {
		if ( $lpname == "" ) {
			// loop 名が無い
			$this->stopflag = true ;
			echo "<b>php source error: loopend() no name.</b><br>\n";
			return ;
		}

		if( isset( $this->arPhpLoopStack ) )
			$nested = count( $this->arPhpLoopStack );
		else
			$nested = 0;

		if ( $nested < 1 ) {
			// loop スタックが無い
			$this->stopflag = true ;
			echo "<b>php source error: loopend( " . $lpname . " ) no stack.</b><br>\n" ;
			return ;
		}
		if ( $this->arPhpLoopStack[ $nested - 1 ][ "lop_self" ] != $lpname ) {
			// 対象 loop 名が loop スタックと一致しない
			$this->stopflag = true ;
			echo "<b>php source error: loopend( " . $lpname . " ) last stack name false.</b><br>\n";
			return ;
		}

		// 正しいループと判断
		unset ( $this->arPhpLoopStack[ $nested - 1 ] ) ;  // 外側の loop スタック破棄

		if ( $this->dbg > 1 ) {
			echo "<!-- loopend [" . $lpname . "] -->\n" ;
		}
	}

	// コード変換と出力
	function flush( $outvar = 0 ) {
		if( isset( $this->arPhpLoopStack ) ){
			$nested = count( $this->arPhpLoopStack ) ;
		}
		else{
			$nested = 0;
		}

		if( $nested > 0 ) {
			// loop スタックが残っている
			$this->stopflag = true ;
			echo "<b>php source error: flush() loop-stack no empty.</b><br>\n";
			return ;
		}
		if ( $this->stopflag == true )
			return;

		if ( $this->dbg == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 01 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
		}

		$this->phase1() ;
		if ( $this->stopflag == true )
			return;

		if ( $this->dbg == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 02 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
		}

		$this->phase2() ;
		if ( $this->stopflag == true )
			return;

		if ( $this->dbg == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 03 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
		}

		ob_start() ;
		if ( $this->dbg > 0 ) {
			echo "\n<!-- this->arDefValue data\n" ;
			print_r ( $this->arDefValue );
			echo "-->\n" ;

			echo "\n<!-- this->arChangeValue data\n" ;
			print_r ( $this->arChangeValue );
			echo "-->\n" ;

			echo "\n<!-- this->arDefList data\n" ;
			print_r ( $this->arDefList );
			echo "-->\n" ;

			echo "\n<!-- this->arPhpLoopList data\n" ;
			print_r ( $this->arPhpLoopList );
			echo "-->\n" ;

			echo "\n<!-- this->arLoopValue data\n" ;
			print_r ( $this->arLoopValue );
			echo "-->\n" ;

			echo "\n<!-- this->arHtmlLoopList data\n" ;
			print_r ( $this->arHtmlLoopList );
			echo "-->\n" ;

			echo "\n<!-- this->arHtmlTemp data\n" ;
			print_r ( $this->arHtmlTemp );
			echo "-->\n" ;

			echo "\n<!-- this->arHtmlTemp2 data\n" ;
			print_r ( $this->arHtmlTemp2 );
			echo "-->\n" ;
		}
		$this->phase3() ;

		if ( $this->dbg == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 04 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
		}

		$contents = ob_get_contents() ;
		ob_end_clean() ;

		$output = $this->phase4( $outvar , $contents ) ;

		if ( $this->dbg == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 05 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
			echo "<b>run loop count = " ;
			print_r ( $this->runloop ) ;
			echo "</b><br>\n" ;
		}

		if( $outvar ) {
			return $output ;
		}
	}




// local-function

	// HTMLソースの解析

	function phase1() {
		if ( ! file_exists ( $this->fname ) ) {
			$this->stopflag = true ;
			echo "<b>php source error: flush() file not open.</b><br>\n";
			return ;
		}
		$fbuf = file( $this->fname ) ;

		// テンプレートHTML 読み込み
		$line_max = count( $fbuf ) ;
		for( $line_cnt = 1; $line_cnt <= $line_max ; $line_cnt ++ ) {
			$this->runloop[0] ++ ;
			$lbuf = $fbuf[ $line_cnt - 1 ] ;

			$cnt_convert = 0 ;
			$cut_out = 0 ;
			$regs1 = array();
			$regs2 = array();
			$regs3 = array();
			if( eregi( '<!--[ \t]+tmpl:(.*)[ \t]+%(.*)%[ \t].*-->' , $lbuf , $regs1 ) ) {
				// %XXX% を含む構文にマッチ
				$regs1[ 1 ] = strtolower( $regs1[ 1 ] ) ;
				if ( $regs1[ 1 ] == 'loop' ) {
					// loop 開始
					$this->phase1_loop( $line_cnt, $regs1[ 2 ] ) ;
					$cut_out = 1 ;
				}
				elseif ( $regs1[ 1 ] == 'ifdef' ) {
					// ifdef 開始
					$this->phase1_ifdef( $line_cnt, $regs1[ 2 ] ) ;
					$cut_out = 1 ;
				}
				elseif ( $regs1[ 1 ] == 'ifndef' ) {
					// ifndef 開始
					$this->phase1_ifndef( $line_cnt, $regs1[ 2 ] ) ;
					$cut_out = 1 ;
				}
				elseif ( $regs1[ 1 ] == 'def' ) {
					// def 定義
					$this->phase1_def( $line_cnt, $regs1[ 2 ] ) ;
					$cut_out = 1 ;
				}
				elseif ( $regs1[ 1 ] == 'ext2php' ) {
					// 拡張子変更
					$this->phase1_ext2php( $line_cnt, $regs1[ 2 ] , $lbuf ) ;
				}
				elseif ( $regs1[ 1 ] == 'ifldef' ) {
					// ifldef
					$cut_out = 1 ;
				}

			}
			elseif( eregi( '<!--[ \t]+tmpl:(.*)[ \t].*-->' , $lbuf , $regs2 ) ) {
				// %XXX% を含まない構文にマッチ
				$regs2[ 1 ] = strtolower( $regs2[ 1 ] ) ;
				if ( $regs2[ 1 ] == 'else' ) {
					// ifdef / ifndef 反転
					$this->phase1_else( $line_cnt ) ;
					$cut_out = 1 ;
				}
				elseif ( $regs2[ 1 ] == 'endifl' ) {
					// endifl
					$cut_out = 1 ;
				}
			}


			if( $cut_out == 0 ) {
				$cnt_convert = preg_match_all( '/%[_\-a-zA-Z][_\-\.0-9a-zA-Z]*%/', $lbuf , $regs3 ) ;
			}


			if( isset( $this->arDefStack ) ){
				$def_nested = count ( $this->arDefStack ) ;
			}
			else{
				$def_nested = 0;
			}

			if( $def_nested == 0 ) {
				$def_list = -1 ;
				if ( $this->dbg > 1 ) {
					$def_root   = '' ;
					$def_parent = '' ;
					$def_self   = '' ;
					$def_nest   = 0 ;
					$def_type   = '' ;
				}
			}
			else {
				$def_list = $this->arDefStack[ $def_nested - 1 ][ 'def_list' ] ;
				if ( $this->dbg > 1 ) {
					$def_root   = $this->arDefList[ $def_list ][ 'def_root' ] ;
					$def_parent = $this->arDefList[ $def_list ][ 'def_parent' ] ;
					$def_self   = $this->arDefList[ $def_list ][ 'def_self' ] ;
					$def_nest   = $this->arDefList[ $def_list ][ 'def_nest' ] ;
					$def_type   = $this->arDefList[ $def_list ][ 'def_type' ] ;
				}
			}

			if( isset( $this->arHtmlLoopStack ) ){
				$lop_nested = count ( $this->arHtmlLoopStack ) ;
			}
			else{
				$lop_nested = 0;
			}

			if( $lop_nested == 0 ) {
				$lop_list = -1 ;
				if ( $this->dbg > 1 ) {
					$lop_root   = '' ;
					$lop_parent = '' ;
					$lop_self   = '' ;
					$lop_nest   = 0 ;
					$lop_list   = -1 ;
				}
			}
			else {
				$lop_list = $this->arHtmlLoopStack[ $lop_nested - 1 ][ 'lop_list' ] ;
				if ( $this->dbg > 1 ) {
					$lop_root   = $this->arHtmlLoopList[ $lop_list ][ 'lop_root' ] ;
					$lop_parent = $this->arHtmlLoopList[ $lop_list ][ 'lop_parent' ] ;
					$lop_self   = $this->arHtmlLoopList[ $lop_list ][ 'lop_self' ] ;
					$lop_nest   = $this->arHtmlLoopList[ $lop_list ][ 'lop_nest' ] ;
				}
			}


			if( isset( $regs1[ 1 ] ) ){
				if ( $regs1[ 1 ] == 'endloop' ) {
					// loop 終了
					$this->phase1_endloop( $line_cnt, $regs1[ 2 ] ) ;
					$cnt_convert = 0;
					$cut_out = 1 ;
				}
			}
			if( isset( $regs2[ 1 ] ) ){
				if ( $regs2[ 1 ] == 'endif' ) {
					// ifdef / ifndef 終了
					$this->phase1_endif( $line_cnt ) ;
					$cnt_convert = 0;
					$cut_out = 1 ;
				}
			}


			if ( $this->dbg < 2 ) {
				$this->arHtmlTemp[] = array(
					'line_cnt'    => $line_cnt ,
					'source'      => $lbuf ,
					'cnt_convert' => $cnt_convert ,

					'def_list'    => $def_list ,

					'lop_list'    => $lop_list ,

					'cut_out'     => $cut_out
				);
			}
			else {
				$this->arHtmlTemp[] = array(
					'line_cnt'    => $line_cnt ,
					'source'      => $lbuf ,
					'cnt_convert' => $cnt_convert ,

					'def_root'    => $def_root ,
					'def_parent'  => $def_parent ,
					'def_self'    => $def_self ,
					'def_nest'    => $def_nest ,
					'def_type'    => $def_type ,
					'def_list'    => $def_list ,

					'lop_root'    => $lop_root ,
					'lop_parent'  => $lop_parent ,
					'lop_self'    => $lop_self ,
					'lop_nest'    => $lop_nest ,
					'lop_list'    => $lop_list ,

					'cut_out'     => $cut_out
				);
			}

		}

		if( isset( $this->arHtmlLoopStack ) ){
			if( count( $this->arHtmlLoopStack ) > 0 ) {
				// loop スタックが残っている
				$this->stopflag = true ;
				echo "<b>html template error: flush() loop-stack no empty.</b><br>\n" ;
				return ;
			}
		}
		if( isset( $this->arDefStack ) ){
			if( count( $this->arDefStack ) > 0 ) {
				// ifdef / ifndef スタックが残っている
				$this->stopflag = true ;
				echo "<b>html template error: flush() ifdef-stack no empty.</b><br>\n" ;
				return ;
			}
		}
	}



	// HTMLソースの解析 - loop

	function phase1_loop( $line_cnt , $lpname ) {
		if( isset( $this->arHtmlLoopStack ) ){
			$nested = count ( $this->arHtmlLoopStack ) ;
		}
		else{
			$nested = 0;
		}

		if( $nested > 0 ) {
			// loop がネストしている
			foreach ( $this->arHtmlLoopStack as $work ) {
				$this->runloop[1] ++ ;
				if ( $work[ 'lop_self' ] == $lpname ) {
					// ネスト中の loop 名が重複
					$this->stopflag = true ;
					echo "<b>html template error(" .$line_cnt. "): tmpl:loop %" . $lpname . "% conflict loop name.</b><br>\n" ;
					return ;
				}
			}
		}

		// 正しいループと判断
		if( isset( $this->arHtmlLoopList ) ){
			$html_loop_count = count( $this->arHtmlLoopList ) ;
		}
		else{
			$html_loop_count = 0;
		}
		$this->arHtmlLoopStack[ $nested ] = array (
			'lop_self'   => $lpname , 
			'lop_list'   => $html_loop_count
		) ;

		if( $nested == 0 ) {
			$this->arHtmlLoopList[] = array (
				'lop_root'   => $this->arHtmlLoopStack[ 0 ][ 'lop_self' ] ,
				'lop_parent' => $this->arHtmlLoopStack[ $nested ][ 'lop_self' ] ,
				'lop_self'   => $lpname ,
				'lop_nest'   => $nested + 1 ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
		else {
			$this->arHtmlLoopList[] = array (
				'lop_root'   => $this->arHtmlLoopStack[ 0 ][ 'lop_self' ] ,
				'lop_parent' => $this->arHtmlLoopStack[ $nested - 1 ][ 'lop_self' ] ,
				'lop_self'   => $lpname ,
				'lop_nest'   => $nested + 1 ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
	}


	// HTMLソースの解析 - endloop

	function phase1_endloop( $line_cnt , $lpname ) {
		if( isset( $this->arHtmlLoopStack ) ){
			$nested = count( $this->arHtmlLoopStack );
		}
		else{
			$nested = 0;
		}

		if ( $nested < 1 ) {
			// loop スタックが無い
			$this->stopflag = true ;
			echo "<b>html template error(" .$line_cnt. "): tmpl:endloop %" . $lpname . "% no loop-stack.</b><br>\n" ;
			return ;
		}
		if ( $this->arHtmlLoopStack[ $nested - 1 ][ 'lop_self' ] != $lpname ) {
			// 対象 loop 名が loop スタックと一致しない
			$this->stopflag = true ;
			echo "<b>html template error(" .$line_cnt. "): tmpl:endloop %" . $lpname . "% last loop-stack name false.</b><br>\n" ;
			return ;
		}

		// 正しいループと判断
		$lop_list = $this->arHtmlLoopStack[ $nested - 1 ][ 'lop_list' ] ;
		$this->arHtmlLoopList[ $lop_list ][ 'html_end' ] = $line_cnt ;

		unset ( $this->arHtmlLoopStack[ $nested - 1 ] ) ;  // 外側の loop スタック破棄
	}


	// HTMLソースの解析 - ifdef

	function phase1_ifdef( $line_cnt , $name ) {
		$name = '%' . $name . '%';

		if( isset( $this->arDefStack ) ){
			$nested = count ( $this->arDefStack ) ;
		}
		else{
			$nested = 0;
		}

		if( $nested > 0 ) {
			// ネストしている
			foreach ( $this->arDefStack as $work ) {
				$this->runloop ++ ;
				if ( $work[ 'def_self' ] == $name ) {
					// ネスト中の ifdef が重複
					$this->stopflag = true ;
					echo "<b>html template error(" .$line_cnt. "): tmpl:ifdef " . $name . " conflict name.</b><br>\n" ;
					return ;
				}
			}
		}

		// 正しいと判断
		if( isset( $this->arDefList ) )
			$def_list_count = count( $this->arDefList );
		else
			$def_list_count = 0;
		$this->arDefStack[ $nested ] = array (
			'def_self'   => $name ,
			'def_type'   => 'ifdef' ,
			'def_list'   => $def_list_count
		) ;

		if( $nested == 0 ) {
			$this->arDefList[] = array (
				'def_root'   => $this->arDefStack[ 0 ][ 'def_self' ] ,
				'def_parent' => $this->arDefStack[ $nested ][ 'def_self' ] ,
				'def_self'   => $name ,
				'def_nest'   => $nested + 1 ,
				'def_type'   => 'ifdef' ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
		else {
			$this->arDefList[] = array (
				'def_root'   => $this->arDefStack[ 0 ][ 'def_self' ] ,
				'def_parent' => $this->arDefStack[ $nested - 1 ][ 'def_self' ] ,
				'def_self'   => $name ,
				'def_nest'   => $nested + 1 ,
				'def_type'   => 'ifdef' ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
	}


	// HTMLソースの解析 - ifndef

	function phase1_ifndef( $line_cnt , $name ) {
		$name = '%' . $name . '%';

		if( isset( $this->arDefStack ) )
			$nested = count ( $this->arDefStack ) ;
		else
			$nested = 0;

		if( $nested > 0 ) {
			// ネストしている
			foreach ( $this->arDefStack as $work ) {
				$this->runloop[1] ++ ;
				if ( $work[ 'def_self' ] == $name ) {
					// ネスト中の ifdef が重複
					$this->stopflag = true ;
					echo "<b>html template error(" .$line_cnt. "): tmpl:ifndef " . $name . " conflict name.</b><br>\n" ;
					return ;
				}
			}
		}

		// 正しいと判断
		$this->arDefStack[ $nested ] = array (
			'def_self'   => $name ,
			'def_type'   => 'ifndef' ,
			'def_list'   => count ( $this->arDefList )
		) ;

		if( $nested == 0 ) {
			$this->arDefList[] = array (
				'def_root'   => $this->arDefStack[ 0 ][ 'def_self' ] ,
				'def_parent' => $this->arDefStack[ $nested ][ 'def_self' ] ,
				'def_self'   => $name ,
				'def_nest'   => $nested + 1 ,
				'def_type'   => 'ifndef' ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
		else {
			$this->arDefList[] = array (
				'def_root'   => $this->arDefStack[ 0 ][ 'def_self' ] ,
				'def_parent' => $this->arDefStack[ $nested - 1 ][ 'def_self' ] ,
				'def_self'   => $name ,
				'def_nest'   => $nested + 1 ,
				'def_type'   => 'ifndef' ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
	}


	// HTMLソースの解析 - else

	function phase1_else( $line_cnt ) {
		if( isset( $this->arDefStack ) )
			$nested = count( $this->arDefStack );
		else
			$nested = 0;

		if ( $nested < 1 ) {
			// loop スタックが無い
			$this->stopflag = true ;
			echo "<b>html template error(" .$line_cnt. "): tmpl:else no stack.</b><br>\n" ;
			return ;
		}

		// 正しいと判断
		$def_list = $this->arDefStack[ $nested - 1 ][ 'def_list' ] ;
		$this->arDefList[ $def_list ][ 'html_end' ] = $line_cnt - 1;

		$def_self = $this->arDefStack[ $nested - 1 ][ 'def_self' ] ;

		// ifdef なら ifndef 、 ifndef なら ifdef に判定を反転させる
		if( $this->arDefStack[ $nested - 1 ][ 'def_type' ] == 'ifdef' )
			$def_type = 'ifndef';
		else
			$def_type = 'ifdef';

		// スタックをそのままに反転内容をセット
		$this->arDefStack[ $nested - 1 ][ 'def_type' ] = $def_type;
		$this->arDefStack[ $nested - 1 ][ 'def_list' ] = count ( $this->arDefList );

		if( $nested == 0 ) {
			$this->arDefList[] = array (
				'def_root'   => $this->arDefStack[ 0 ][ 'def_self' ] ,
				'def_parent' => $this->arDefStack[ $nested ][ 'def_self' ] ,
				'def_self'   => $def_self ,
				'def_nest'   => $nested ,
				'def_type'   => $def_type ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
		else {
			$this->arDefList[] = array (
				'def_root'   => $this->arDefStack[ 0 ][ 'def_self' ] ,
				'def_parent' => $this->arDefStack[ $nested - 1 ][ 'def_self' ] ,
				'def_self'   => $def_self ,
				'def_nest'   => $nested ,
				'def_type'   => $def_type ,
				'html_start' => $line_cnt ,  // HTML 上の loop 開始行
				'html_end'   => -1           // HTML 上の loop 終了行（初期値：-1）
			);
		}
	}


	// HTMLソースの解析 - endif

	function phase1_endif( $line_cnt ) {
		if( isset( $this->arDefStack ) )
			$nested = count( $this->arDefStack );
		else
			$nested = 0;

		if ( $nested < 1 ) {
			// loop スタックが無い
			$this->stopflag = true ;
			echo "<b>html template error(" .$line_cnt. "): tmpl:endif no stack.</b><br>\n" ;
			return ;
		}

		// 正しいと判断
		$def_list = $this->arDefStack[ $nested - 1 ][ 'def_list' ] ;
		$this->arDefList[ $def_list ][ 'html_end' ] = $line_cnt ;

		unset ( $this->arDefStack[ $nested - 1 ] ) ;  // 外側のスタック破棄
	}


	// HTMLソースの解析 - def

	function phase1_def( $line_cnt , $name ) {
		$name = '%' . $name . '%';
		$this->arDefValue[ $name ] = true ; 

		if ( $this->dbg > 1 ) {
			echo "<!-- HTML(" .$line_cnt. "): tmpl:def " . $name . " assign -->\n" ;
		}
	}


	// HTMLソースの解析 - ext2php

	function phase1_ext2php( $line_cnt , $ext , &$lbuf ) {
		$work = eregi_replace( '<!--[ \t]+tmpl:ext2php[ \t]+(%.*%)[ \t].*-->' , '' , $lbuf );
		$ext = eregi_replace( '%' , '' , $ext );
		$lbuf = eregi_replace( $ext , 'php' , $work );
	}




	// ifdef / ifndef の解析

	function phase2() {
		$del_nested = -1 ;  // 1以上なら、そのネストを破棄
		$del_type   = '' ;
		$del_self   = '' ;

		if( ( ! isset( $this->arDefList ) ) or ( count( $this->arDefList ) == 0 ) ) {
			// HTML上に ifdef / ifndef が存在しないなら
			$this->arHtmlTemp2 = $this->arHtmlTemp ;
			return ;
		}


		foreach ( $this->arHtmlTemp as $key => $work ) {
			$this->runloop[2] ++ ;

			$def_list = $work[ 'def_list' ] ;
			if( $def_list == -1 )
				$def_nest = 0;
			else
				$def_nest = $this->arDefList[ $def_list ][ 'def_nest' ] ;

			if ( $def_nest == 0 ) {
				// ifdef / ifndef ブロックではない
				$this->arHtmlTemp2[ $key ] = $work ;
				continue ;
			}

			$def_root   = $this->arDefList[ $def_list ][ 'def_root' ] ;
			$def_parent = $this->arDefList[ $def_list ][ 'def_parent' ] ;
			$def_self   = $this->arDefList[ $def_list ][ 'def_self' ] ;
			$def_type   = $this->arDefList[ $def_list ][ 'def_type' ] ;

			if ( $del_nested == -1 ) {
				if ( ( $def_type == "ifdef" ) and ( ! isset ( $this->arDefValue[ $def_self ] ) ) ) {
					// 破棄
					$del_nested = $def_nest ;
					$del_type   = $def_type ;
					$del_self   = $def_self ;
					continue ;
				}
				if ( ( $def_type == "ifndef" ) and ( isset ( $this->arDefValue[ $def_self ] ) ) ) {
					// 破棄
					$del_nested = $def_nest ;
					$del_type   = $def_type;
					$del_self   = $def_self ;
					continue ;
				}

				$this->arHtmlTemp2[ $key ] = $work ;
			}
			else {
				if( $def_nest > $del_nested ) {
					// ネストの深いブロックも破棄
					continue ;
				}
				if( $def_nest == $del_nested ) {
					// ネストの深さは同じだが、条件変化をチェック
					if ( ( $def_self == $del_self ) and ( $def_type == $del_type ) ) {
						// 条件変化無し
						continue ;
					}

					// 条件変化有り
					if ( ( $def_type == "ifdef" ) and ( ! isset ( $this->arDefValue[ $def_self ] ) ) ) {
						// 破棄
						$del_nested = $def_nest ;
						$del_type   = $def_type ;
						$del_self   = $def_self ;
						continue ;
					}
					if ( ( $def_type == "ifndef" ) and ( isset ( $this->arDefValue[ $def_self ] ) ) ) {
						// 破棄
						$del_nested = $def_nest ;
						$del_type   = $def_type ;
						$del_self   = $def_self ;
						continue ;
					}
				}
				if( $def_nest < $del_nested ) {
					// ネストの深さが変化したので、再度判断
					if ( ( $def_type == "ifdef" ) and ( ! isset ( $this->arDefValue[ $def_self ] ) ) ) {
						// 破棄
						$del_nested = $def_nest ;
						$del_type   = $def_type ;
						$del_self   = $def_self ;
						continue ;
					}
					if ( ( $def_type == "ifndef" ) and ( isset ( $this->arDefValue[ $def_self ] ) ) ) {
						// 破棄
						$del_nested = $def_nest ;
						$del_type   = $def_type ;
						$del_self   = $def_self ;
						continue ;
					}
				}

				$del_nested = -1 ;
				$del_type   = '' ;
				$del_self   = '' ;
				$this->arHtmlTemp2[ $key ] = $work ;
			}
		}
	}




	// loop の展開、置換処理

	function phase3() {
		$skip = -1 ;  // スキップする行

		foreach ( $this->arHtmlTemp2 as $key => $work ) {
			$this->runloop[3] ++ ;
			if ( $key < $skip ) {
				continue ;
			}
			$skip = -1 ;

			$html_list = $this->arHtmlTemp2[ $key ][ 'lop_list' ] ;
			if( $html_list == -1 )
				$lop_nest =  0;
			else
				$lop_nest = $this->arHtmlLoopList[ $html_list ][ 'lop_nest' ] ;

			if ( $lop_nest == 0 ) {
				if ( $work[ 'cut_out' ] == 1 ) {
					// 出力対象でない
					continue ;
				}
				// loop でない
				if ( $work[ 'cnt_convert' ] == 0 ) {
					echo $work[ 'source' ] ;  // 置換無し
					continue ;
				}

				echo $this->phase3_convert( $work[ 'source' ] ) ;  // 置換有り
				continue ;
			}

			// loop 処理
			$skip = $this->phase3_loop( $key , 1 ) ;
		}
	}


	// loop の展開、置換処理 - １行置換

	function phase3_convert( $source ) {
		$cnt = preg_match_all( '/%[_\-a-zA-Z][_\-\.0-9a-zA-Z]*%/' , $source , $regs , PREG_SET_ORDER ) ;
		if( $cnt ) {
			// %abc% = 123 を実現するため、逆順で変換
			for( $i = ( $cnt - 1 ) ; $i >= 0 ; $i -- ) {
				if ( isset( $this->arChangeValue[ $regs[$i][0] ] ) ) {
					$tmp = str_replace( $regs[$i][0] , $this->arChangeValue[ $regs[$i][0] ] , $source ) ;
					$source = $tmp ;
				}
				else{
					if( UNDEF2BLANK ){
						// 値が assign されていない場合、消去する
						$tmp = str_replace( $regs[$i][0] , "" , $source ) ;
						$source = $tmp ;
					}
				}
			}
		}

		return $source ;
	}


	// loop の展開、置換処理 - loop 展開

	function phase3_loop( $key , $pcnt ) {
		$html_list  = -1;
		$php_list   = -1;
		$html_start = -1;
		$html_end   = -1;
		$lop_cnt    = -1;

		$html_list  = $this->arHtmlTemp2[ $key ][ 'lop_list' ] ;

		$lop_root   = $this->arHtmlLoopList[ $html_list ][ 'lop_root'   ] ;
		$lop_parent = $this->arHtmlLoopList[ $html_list ][ 'lop_parent' ] ;
		$lop_self   = $this->arHtmlLoopList[ $html_list ][ 'lop_self'   ] ;
		$lop_nest   = $this->arHtmlLoopList[ $html_list ][ 'lop_nest'   ] ;
		$html_start = $this->arHtmlLoopList[ $html_list ][ 'html_start' ] ;
		$html_end   = $this->arHtmlLoopList[ $html_list ][ 'html_end'   ] ;

		// PHP側 loop リスト検索
		if( (isset($this->arPhpLoopList)) and ( count( $this->arPhpLoopList ) > 0 )) {
			foreach( $this->arPhpLoopList as $key3 => $work ) {
				$this->runloop[4] ++ ;
				if( ( $work[ 'lop_root'   ] == $lop_root   ) and
					( $work[ 'lop_parent' ] == $lop_parent ) and
					( $work[ 'lop_pcnt'   ] == $pcnt       ) and
					( $work[ 'lop_self'   ] == $lop_self   ) and
					( $work[ 'lop_nest'   ] == $lop_nest   ) ) {
					// loop リストを見つけた
					$php_list   = $key3 ;
					$lop_allcnt = $work[ 'lop_allcnt' ] ;

					unset( $this->arPhpLoopList[ $key3 ] ) ;  // 処理済みデータの破棄
					break ;
				}
			}
		}

		// HTML側、PHP側のどちらかのループリストが見つからない場合
		if ( ( $html_list == -1 ) or ( $php_list == -1 ) ) {
			return ( $key + 1 ) ;  // エラーの場合、現在の行を返す
		}

		// PHP側 ループ数
		for( $plop = 1 ; $plop <= $lop_allcnt ; $plop ++ ) {

			// 置換対象データを取り出す
			$cnv_cnt = 0;
			if( count( $this->arLoopValue ) > 0 ) {
				foreach ( $this->arLoopValue as $key4 => $work ) {
					$this->runloop[5] ++ ;
					if( ( $work[ 'lop_list' ] == $php_list ) and
						( $work[ 'lop_cnt'  ] == $plop     ) ) {
						// 対象データなら
						$convert = $work[ 'value' ];
						$cnv_cnt ++ ;

						unset( $this->arLoopValue[ $key4 ] ) ;  // 処理済みデータの破棄
						break ;
					}
				}
			}

			// HTML側 ループ数
			$skip = -1 ;  // スキップする行
			$out_flag = 0 ;  // 出力フラグ（ifldef用）
			for( $hlop = ( $html_start - 1 ) ; $hlop < $html_end ; $hlop ++ ) {
				$this->runloop[6] ++ ;
				if ( $hlop < $skip ) {
					continue ;
				}
				$skip = -1 ;

				if ( ! isset( $this->arHtmlTemp2[ $hlop ] ) ) {
					// ifdef / ifndef ブロックで除去済み
					continue ;
				}

				$work = $this->arHtmlTemp2[ $hlop ] ;
				$lbuf = $work[ 'source' ];
				if( $out_flag == 0 ){
					// ifldef
					if( eregi( '<!--[ \t]+tmpl:ifldef[ \t]+(%.*%)[ \t].*-->' , $lbuf , $regs ) ) {
						if ( ! isset( $convert[ $regs[1] ] ) ) {
							$out_flag = 1 ;  // loop 中の定義が存在しない場合
							continue ;
						}
					}
				}
				else{
					// endifl
					if( eregi( '<!--[ \t]+tmpl:endifl[ \t].*-->' , $lbuf , $regs ) ) {
						$out_flag = 0 ;
						continue ;
					}
				}
				if( $out_flag ){
					continue ;  // ifldef で出力しない
				}

				$lop_nest2  = $this->arHtmlLoopList[ $work[ 'lop_list' ] ][ 'lop_nest' ] ;
				if( $lop_nest2 > $lop_nest ) {
					// 今より深いネストを見つけた
					$skip = $this->phase3_loop( $hlop , $plop ) ;  // 再帰
					continue ;
				}

				if ( $work[ 'cut_out' ] == 1 ) {
					// 出力対象でない
					continue ;
				}

				if( $work[ 'cnt_convert' ] == 0 )
					echo $work[ 'source' ];  // 置換無し
				else
					echo $this->phase3_loopconvert( $work[ 'source' ] , $convert ) ;  // 置換有り

			}
		}

		return $html_end ;
	}


	// loop の展開、置換処理 - loop 中の１行置換

	function phase3_loopconvert( $source , $convert ) {
		// 置換対象データを取り出す
		if( isset( $this->arChangeValue ) )
			$change_value_count = count( $this->arChangeValue );
		else
			$change_value_count = 0;

		$cnv_cnt = count( $convert ) + $change_value_count;

		if ( $cnv_cnt == 0 ) {
			// 置換対象が無い
			return $source ;
		}

		$cnt = preg_match_all( '/%[_\-a-zA-Z][_\-\.0-9a-zA-Z]*%/' , $source , $regs , PREG_SET_ORDER ) ;
		if( $cnt ) {
			// %abc% = 123 を実現するため、逆順で変換
			for( $i = ( $cnt - 1 ) ; $i >= 0 ; $i -- ) {
				// loop 内置換処理
				if ( isset( $convert[ $regs[$i][0] ] ) ) {
					$tmp = str_replace( $regs[$i][0] , $convert[ $regs[$i][0] ] , $source ) ;
					$source = $tmp ;
				}
				// loop 外置換処理
				if ( isset( $this->arChangeValue[ $regs[$i][0] ] ) ) {
					$tmp = str_replace( $regs[$i][0] , $this->arChangeValue[ $regs[$i][0] ] , $source ) ;
					$source = $tmp ;
				}
				else{
					if( UNDEF2BLANK ){
						// 値が assign されていない場合、消去する
						$tmp = str_replace( $regs[$i][0] , "" , $source ) ;
						$source = $tmp ;
					}
				}
			}
		}

		return $source ;

	}




	// 漢字コードの変換

	function phase4( $outvar , $contents ) {
		if( $outvar ) {
			if ( $this->kconv ) {
				return( mb_convert_encoding( &$contents , $this->kcout , $this->kcin ) ) ;
			} else {
				return( $contents ) ;
			}
		} else {
			if ( $this->kconv ) {
				echo mb_convert_encoding( &$contents , $this->kcout , $this->kcin ) ;
			} else {
				echo $contents ;
			}
		}
	}


}
// endof Tmpl-class
?>
