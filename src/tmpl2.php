<?php
declare(strict_types=1);
// @author    Yoshiaki Tahara
// @license   MIT License
// @copyright (c) 2026 Yoshiaki Tahara
// 
// テンプレートクラス
// 
// ファイル中の%[_\-a-zA-Z][_\-\.0-9a-zA-Z]*%を指定した文字列に
// 置換を行います。
// 
// <!-- tmpl:loop %XXX% --><!-- tmpl:endloop %XXX% -->
//   ループにも対応しています(ループのネストは可)。
//
// <!-- tmpl:ifdef %XXX% --><!-- tmpl:else --><!-- tmpl:endif -->
//   変数の定義があったときだけ出力する処理も可能。
// 
// <!-- tmpl:ifndef %XXX% --><!-- tmpl:else --><!-- tmpl:endif -->
//   変数の定義がないときだけ出力する処理も可能。
// 
// <!-- tmpl:ifldef %XXX% --><!-- tmpl:else --><!-- tmpl:endifl -->
//   LOOP内で変数の定義があったときだけ出力する処理も可能。
// 
// <!-- tmpl:iflndef %XXX% --><!-- tmpl:else --><!-- tmpl:endifl -->
//   LOOP内で変数の定義がないときだけ出力する処理も可能。
//
// テンプレートの1行のMAXは16KBまで。
//
// BASE -- Id: tmpl2.class.inc,v 1.9 2001/12/24 14:32:08 kawa Exp --
// $Id: tmpl2.class.php,v 1.18 2002/10/20 07:53:53 satou Exp $
// $Id: lib.tmpl2.class.php,v 1.26 2025/11/25 14:08:08 Yoshiaki Tahara $
//
// Tmpl2 2.00 beta refactoring draft 2026/08/20 (PHP 7.4 compatible)
// PHP loop state converted from associative arrays to LoopFrame/LoopInstance/LoopRow.
// This is a compatibility-oriented draft; parser/condition refactoring remains pending.
//
//
namespace Tmpl2;

const UNDEF2BLANK = false;
const DEF_DEBUG = false;
final class Directive
{
    public const IFDEF   = 'ifdef';
    public const IFNDEF  = 'ifndef';
    public const IFLDEF  = 'ifldef';
    public const IFLNDEF = 'iflndef';
    public const ELSE_   = 'else';
    public const ENDIF   = 'endif';
    public const ENDIFL  = 'endifl';
    public const LOOP    = 'loop';
    public const ENDLOOP = 'endloop';

    private string $type;
    private ?string $variableName;

    public function __construct(
        string $type,
        ?string $variableName = null
    ) {
        $this->type = $type;
        $this->variableName = $variableName;
    }
    public function type(): string { return $this->type; }
    public function variableName(): ?string { return $this->variableName; }
    public function isCondition(): bool
    {
        return in_array(
            $this->type,
            [
                self::IFDEF,
                self::IFNDEF,
                self::IFLDEF,
                self::IFLNDEF,
            ],
            true
        );
    }
    public function isLocalCondition(): bool
    {
        return $this->type === self::IFLDEF
            || $this->type === self::IFLNDEF;
    }
    public static function reverse(string $type): string
    {
        switch ($type) {
            case self::IFDEF:
                return self::IFNDEF;

            case self::IFNDEF:
                return self::IFDEF;

            case self::IFLDEF:
                return self::IFLNDEF;

            case self::IFLNDEF:
                return self::IFLDEF;
        }
        throw new LogicException(
            'Cannot reverse directive type: ' . $type
        );
    }
}
/*
arDefList
*/
final class ConditionBlock
{
    private string $type;
    private string $variableName;
    private int $startLine;
    private ?int $elseLine;
    private ?int $endLine;
    private int $depth;
    private bool $elseBranch;

    public function __construct(
        string $type,
        string $variableName,
        int $startLine,
        int $depth,
        bool $elseBranch = false
    ) {
        $this->type = $type;
        $this->variableName = $variableName;
        $this->startLine = $startLine;
        $this->elseLine = null;
        $this->endLine = null;
        $this->depth = $depth;
        $this->elseBranch = $elseBranch;
    }
    public function type(): string { return $this->type; }
    public function variableName(): string { return $this->variableName; }
    public function startLine(): int { return $this->startLine; }
    public function elseLine(): ?int { return $this->elseLine; }
    public function endLine(): ?int { return $this->endLine; }
    public function depth(): int { return $this->depth; }
    public function isClosed(): bool { return $this->endLine !== null; }
    public function isElseBranch(): bool { return $this->elseBranch; }
    public function setElseLine(int $line): void
    {
        if ($this->elseLine !== null) {
            throw new LogicException(
                'Condition block already contains an else directive.'
            );
        }

        $this->elseLine = $line;
    }
    public function isLocalCondition(): bool
    {
        return $this->type === Directive::IFLDEF
            || $this->type === Directive::IFLNDEF;
    }
    public function close(int $line): void
    {
        if ($this->endLine !== null) {
            throw new LogicException(
                'Condition block is already closed.'
            );
        }

        $this->endLine = $line;
    }
}
/*
arDefStack ifdef／ifndef ブロックの入れ子状態
*/
final class ConditionFrame
{
    private ConditionBlock $block;
    private int $blockIndex;

    public function __construct(
        ConditionBlock $block,
        int $blockIndex
    ) {
        $this->block = $block;
        $this->blockIndex = $blockIndex;
    }
    public function block(): ConditionBlock { return $this->block; }
    public function blockIndex(): int { return $this->blockIndex; }
    public function variableName(): string { return $this->block->variableName(); }
    public function type(): string { return $this->block->type(); }
}

/**
 * PHP 側で構築中のループ実体。
 * 旧 arPhpLoopList の1要素に相当します。
 */
final class LoopInstance
{
    private $name;
    private $rootName;
    private $parentName;
    private $parentRowNumber;
    private $depth;
    private $rowCount;

    public function __construct(
        string $name,
        string $rootName,
        string $parentName,
        int $parentRowNumber,
        int $depth,
        int $rowCount = 0
    ) {
        $this->name = $name;
        $this->rootName = $rootName;
        $this->parentName = $parentName;
        $this->parentRowNumber = $parentRowNumber;
        $this->depth = $depth;
        $this->rowCount = $rowCount;
    }
    public function name(): string { return $this->name; }
    public function rootName(): string { return $this->rootName; }
    public function parentName(): string { return $this->parentName; }
    public function parentRowNumber(): int { return $this->parentRowNumber; }
    public function depth(): int { return $this->depth; }
    public function rowCount(): int { return $this->rowCount; }
    public function incrementRowCount(): void { $this->rowCount++; }
}
/**
 * loopnext() で確定したループ1行分。
 * 旧 arLoopValue の1要素に相当します。
 */
final class LoopRow
{
    private $instance;
    private $rowNumber;
    private $values;

    public function __construct(LoopInstance $instance, int $rowNumber, array $values)
    {
        $this->instance = $instance;
        $this->rowNumber = $rowNumber;
        $this->values = $values;
    }

    public function instance(): LoopInstance { return $this->instance; }
    public function rowNumber(): int { return $this->rowNumber; }
    public function values(): array { return $this->values; }
}


/**
 * 現在構築中のループフレーム。
 * 旧 arPhpLoopStack と arOneLoopValue の1階層分に相当します。
 */
final class LoopFrame
{
    private $instance;
    private $rowNumber;
    private $currentValues = [];

    public function __construct(LoopInstance $instance, int $rowNumber = 1)
    {
        $this->instance = $instance;
        $this->rowNumber = $rowNumber;
    }

    public function name(): string { return $this->instance->name(); }
    public function instance(): LoopInstance { return $this->instance; }
    public function rowNumber(): int { return $this->rowNumber; }
    public function assign(string $key, string $value): void { $this->currentValues[$key] = $value; }
    public function define(string $key): void { $this->currentValues[$key] = ''; }

    public function commitRow(): LoopRow
    {
        $row = new LoopRow(
            $this->instance,
            $this->rowNumber,
            $this->currentValues
        );

        $this->currentValues = [];
        $this->rowNumber++;
        $this->instance->incrementRowCount();

        return $row;
    }
}
/**
 * 現在構築中のループフレーム。
 * 旧 arHtmlLoopStack に相当します。
 */
final class HtmlLoopBlock
{
    private string $name;
    private string $rootName;
    private string $parentName;
    private int $startLine;
    private ?int $endLine;
    private int $depth;

    public function __construct(
        string $name,
        string $rootName,
        string $parentName,
        int $startLine,
        int $depth
    ) {
        $this->name = $name;
        $this->rootName = $rootName;
        $this->parentName = $parentName;
        $this->startLine = $startLine;
        $this->endLine = null;
        $this->depth = $depth;
    }

    public function name(): string { return $this->name; }
    public function rootName(): string { return $this->rootName; }
    public function parentName(): string { return $this->parentName; }
    public function startLine(): int { return $this->startLine; }
    public function endLine(): ?int { return $this->endLine; }
    public function depth(): int { return $this->depth; }
    public function close(int $lineNumber): void { $this->endLine = $lineNumber; }
}

/**
 * 現在構築中のループフレーム。
 * 旧 arHtmlLoopListに相当します。
 */
final class HtmlLoopFrame
{
    private HtmlLoopBlock $block;
    private int $blockIndex;

    public function __construct(
        HtmlLoopBlock $block,
        int $blockIndex
    ) {
        $this->block = $block;
        $this->blockIndex = $blockIndex;
    }

    public function block(): HtmlLoopBlock { return $this->block; }
    public function blockIndex(): int { return $this->blockIndex; }
    public function name(): string { return $this->block->name(); }
}
final class TemplateLine
{
    private string $source;
    private int $lineNumber;
    private int $convertCount;
    private int $conditionBlockIndex;
    private int $htmlLoopBlockIndex;
    private bool $cutOut;

    public function __construct(
        string $source,
        int $lineNumber,
        int $convertCount,
        int $conditionBlockIndex,
        int $htmlLoopBlockIndex,
        bool $cutOut
    ) {
        $this->source = $source;
        $this->lineNumber = $lineNumber;
        $this->convertCount = $convertCount;
        $this->conditionBlockIndex = $conditionBlockIndex;
        $this->htmlLoopBlockIndex = $htmlLoopBlockIndex;
        $this->cutOut = $cutOut;
    }

    public function source(): string { return $this->source; }
    public function lineNumber(): int { return $this->lineNumber; }
    public function convertCount(): int { return $this->convertCount; }
    public function conditionBlockIndex(): int { return $this->conditionBlockIndex; }
    public function htmlLoopBlockIndex(): int { return $this->htmlLoopBlockIndex; }
    public function isCutOut(): bool { return $this->cutOut; }
}

class Tmpl2
{
    public const UNDEF2BLANK = false;
    public const CHARACTERCODE = 'UTF-8';
    public const SMK = '%';

    private string $fname = './template.htm';
    private string $kcout = self::CHARACTERCODE;
    private int $fquotes = \ENT_QUOTES | ENT_SUBSTITUTE;
    private int $dbg = 0;

    /** @var list<string> */
    private array $membuff = [];
    private bool $stopflag = false;

	private const RUN_PHASE1_PARSE = 0;
	private const RUN_STACK_SEARCH = 1;
	private const RUN_PHASE2       = 2;
	private const RUN_PHASE3       = 3;
	private const RUN_LOOP_SEARCH  = 4;
	private const RUN_VALUE_SEARCH = 5;
	private const RUN_HTML_LOOP    = 6;

	/** @var array<int, int> */
	private array $runloop = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0,];

    /** @var list<LoopFrame> */
    private array $arPhpLoopStack = [];
    /** @var list<LoopInstance> */
    private array $arPhpLoopList = [];
    /** @var list<LoopRow> */
    private array $arLoopValue = [];

    /** @var list<HtmlLoopFrame> */
    private array $arHtmlLoopStack = [];
    /** @var list<HtmlLoopBlock> */
    private array $arHtmlLoopList = [];

    /** @var list<TemplateLine> */
    private array $arHtmlTemp = [];
    /** @var list<TemplateLine> */
    private array $arHtmlTemp2 = [];

    /** @var array<string, true> */
    private array $arDefValue = [];
    /** @var array<string, string> */
    private array $arChangeValue = [];

	/** @var list<ConditionFrame> */
	private array $conditionStack = [];

	/* 名前変更 */
	/** @var list<ConditionBlock> */
	private array $conditionBlocks = [];
	// コンストラクタ
	public function __construct(
		string $filename = '',
		string $ocode = self::CHARACTERCODE
	) {
        $this->loadTemplate($filename);
		if ($ocode !== '') {
			$this->kcout = $ocode;
		}
	}
    public function loadTemplate(string $filename): void
    {
        $this->fname = $filename;
    }
    public function MemoryTmpl(string $buff): void {
        $this->set_MemoryTmpl($buff);
    }
	public function set_MemoryTmpl(string $buff): void {
		$this->membuff = explode("\n", $buff);
	}
	// デバッグモードフラグ設定
	// -1:処理時間計測、ループ回数報告
	//  0:通常モード
	//  1:配列のみ表示
	//  2:全ての情報を表示
	public function dbgmode( $dbgflag ) {
		$this->dbg = $dbgflag ;
		if ( $dbgflag == -1 ) {
			list($usec, $sec) = explode(" ",microtime());
			echo " 00 : " . ((float)$sec + (float)$usec) . " sec<br>\n";
		}
	}

    public function setquotes(int $q): void {
        $this->fquotes = $q;
    }
    public function setencoding(string $code): void {
        $this->kcout = $code;
    }
	// テンプレートファイル名の変更
	public function set_fname( $filename ): void {
		if ( ! file_exists ( $filename ) ) {
			$this->stopflag = true ;
			echo "<b>php source error: set_fname() file not open.</b><br>\n";
			return ;
		}
		$this->fname = $filename ;
	}

	// 置換用変数の追加
	public function assign(string $name, $value): void
	{
		$key = self::SMK . $name . self::SMK;
		$value2 = $this->value2quotes($value);

		if ($this->arPhpLoopStack === []) {
			$this->arChangeValue[$key] = $value2;
		} else {
			$this->currentLoopFrame()->assign($key, $value2);
		}

		if ($this->dbg > 1) {
			echo '<!-- assign [' . $key . '] -->' . "\n";
		}
	}

	/**
	 * HTML/XML向けの既定エスケープ。
	 * プレーンテキスト用途では quotes(0) または将来のFormatter差し替えを利用します。
	 */
	public function value2quotes($value): string
	{
		if ($value === null) {
			$text = '';
		} elseif (is_bool($value)) {
			$text = $value ? '1' : '';
		} elseif (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
			$text = (string)$value;
		} else {
			throw new InvalidArgumentException('Template value must be scalar, null, or implement __toString().');
		}

		if ($this->fquotes === 0) {
			return $text;
		}

		return htmlspecialchars($text, $this->fquotes, $this->kcout);
	}

	private function currentLoopFrame(): LoopFrame
	{
		if ($this->arPhpLoopStack === []) {
			throw new LogicException('No active loop frame.');
		}

		return $this->arPhpLoopStack[array_key_last($this->arPhpLoopStack)];
	}
	/* ifdefに関する関数 */
	private function beginCondition(
    	string $type,
    	string $variableName,
    	int $lineNumber,
        bool $elseBranch = false): void 
	{
    	$depth = count($this->conditionStack);

    	$block = new ConditionBlock(
        	$type,
        	$variableName,
        	$lineNumber,
        	$depth + 1,
            $elseBranch
    	);
		$blockIndex = count($this->conditionBlocks);

    	$this->conditionBlocks[] = $block;
    	$this->conditionStack[] = new ConditionFrame($block,$blockIndex);
	}
	/* elseに関する関数 */
	private function currentConditionFrame(): ConditionFrame
	{
    	if ($this->conditionStack === []) {
        	throw new LogicException(
            	'No active condition block.'
        	);
    	}

    	return $this->conditionStack[
        	array_key_last($this->conditionStack)
    	];
	}
	private function setConditionElse(int $lineNumber): void
	{
    	$frame = $this->currentConditionFrame();

    	$frame->block()->setElseLine($lineNumber);
	}
	private function endCondition(int $lineNumber): void
	{
    	/** @var ConditionFrame $frame */
    	$frame = array_pop($this->conditionStack);

    	$frame->block()->close($lineNumber);
	}
    private function appendPhase1Line(int $line_cnt,string $line,bool $cutOut = false): void 
    {
        $conditionBlockIndex = -1;
        $htmlLoopBlockIndex = -1;

        if ($this->conditionStack !== []) {
            $conditionBlockIndex =
                $this->currentConditionFrame()->blockIndex();
        }

        if ($this->arHtmlLoopStack !== []) {
            $htmlLoopBlockIndex =
                $this->arHtmlLoopStack[
                    array_key_last($this->arHtmlLoopStack)
                ]->blockIndex();
        }

        $convertCount = 0;
        if (!$cutOut) {
            $convertCount = preg_match_all(
                '/%[_\-a-zA-Z][_\-\.0-9a-zA-Z]*%/',$line);
        }

        $this->arHtmlTemp[] = new TemplateLine(
            $line,
            $line_cnt,
            $convertCount,
            $conditionBlockIndex,
            $htmlLoopBlockIndex,
            $cutOut
        );
    }

 	private function parseDirective(string $line): ?Directive
	{
    	$pattern =
        	'/<!--\s*tmpl:'
        	. '(ifdef|ifndef|ifldef|iflndef|loop|endloop)'
        	. '\s+%([_\-a-z][_\-\.0-9a-z]*)%'
        	. '\s*-->/i';

    	if (preg_match($pattern, $line, $matches)) {
        	return new Directive(
            	strtolower($matches[1]),
            	$matches[2]
        	);
    	}

    	$pattern =
        	'/<!--\s*tmpl:(else|endif|endifl)\s*-->/i';

    	if (preg_match($pattern, $line, $matches)) {
        	return new Directive(
            	strtolower($matches[1])
        	);
    	}

    	return null;
	}


	// ifdef / ifndef 展開用変数の追加
	public function assign_def(string $name): void {
		$this->arDefValue[self::SMK . $name . self::SMK] = true; 
		if ( $this->dbg > 1 ) {
			echo "<!-- assign_def [%" . $name . "%] = \"true\" -->\n" ;
		}
	}

	// 現在のループ行へ存在フラグを追加（ifldef用）
	public function assign_local_def(string $name): void
	{
		$this->currentLoopFrame()->define(self::SMK . $name . self::SMK);
		if ( $this->dbg > 1 ) {
			echo "<!-- assign_def [%" . $name . "%] = \"true\" -->\n" ;
		}
	}

	// ループ名設定
	public function loopset(string $lpname): void
	{
		if ($lpname === '') {
			$this->templateError('loopset() no name.');
			return;
		}

		$nested = count($this->arPhpLoopStack);
		foreach ($this->arPhpLoopStack as $frame) {
			if ($frame->name() === $lpname) {
				$this->templateError('loopset(' . $lpname . ') conflict name.');
				return;
			}
		}

		if ($nested === 0) {
			$rootName = $lpname;
			// 旧実装との互換性のため、ルートの親は自分自身、親行番号は1。
			$parentName = $lpname;
			$parentRowNumber = 1;
		} else {
			$parent = $this->currentLoopFrame();
			$rootName = $this->arPhpLoopStack[0]->name();
			$parentName = $parent->name();
			$parentRowNumber = $parent->rowNumber();
		}

		$instance = new LoopInstance(
			$lpname,
			$rootName,
			$parentName,
			$parentRowNumber,
			$nested + 1
		);

		$this->arPhpLoopStack[] = new LoopFrame($instance);
		$this->arPhpLoopList[] = $instance;

		if ($this->dbg > 1) {
			echo '<!-- loopset [' . $lpname . '] -->' . "\n";
		}
	}

	// ループの繰り返し
	public function loopnext(string $lpname): void
	{
		if ($lpname === '') {
			$this->templateError('loopnext() no name.');
			return;
		}
		if ($this->arPhpLoopStack === []) {
			$this->templateError('loopnext(' . $lpname . ') no stack.');
			return;
		}

		$frame = $this->currentLoopFrame();
		if ($frame->name() !== $lpname) {
			$this->templateError('loopnext(' . $lpname . ') last stack name false.');
			return;
		}

		$this->arLoopValue[] = $frame->commitRow();

		if ($this->dbg > 1) {
			echo '<!-- loopnext [' . $lpname . '] -->' . "\n";
		}
	}

	// ループ終了
	public function loopend(string $lpname): void
	{
		if ($lpname === '') {
			$this->templateError('loopend() no name.');
			return;
		}
		if ($this->arPhpLoopStack === []) {
			$this->templateError('loopend(' . $lpname . ') no stack.');
			return;
		}

		$frame = $this->currentLoopFrame();
		if ($frame->name() !== $lpname) {
			$this->templateError('loopend(' . $lpname . ') last stack name false.');
			return;
		}

		array_pop($this->arPhpLoopStack);

		if ($this->dbg > 1) {
			echo '<!-- loopend [' . $lpname . '] -->' . "\n";
		}
	}

	private function templateError(string $message): void
	{
		$this->stopflag = true;
		echo '<b>php source error: '
			. htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, self::CHARACTERCODE)
			. '</b><br>' . "\n";
	}

	// コード変換と出力
	public function flush( $outvar = 0 ) {
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

			echo "\n<!-- this->conditionBlocks data\n" ;
			print_r ( $this->conditionBlocks );
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

    public function render() {
        return ($this->flush());
    }



    // local-function
    private function validateConditionStack(): void
    {
        if ($this->conditionStack === []) {
            return;
        }

        $frame = count($this->conditionStack);

        $this->stopflag = true;

        echo "<b>html template error: condition not closed.</b><br>\n";
    }

	// HTMLソースの解析
    private function phase1(): void
    {
        $this->arHtmlTemp = [];

        $lines = $this->membuff !== []
            ? $this->membuff　: file($this->fname);

        foreach ($lines as $index => $line) {
            $line_cnt = $index + 1;
            $this->runloop[self::RUN_PHASE1_PARSE]++;
            $directive = $this->parseDirective($line);
            $cut_out = false;
            if ($directive !== null) {
                switch ($directive->type()) {
                    case Directive::LOOP:
                        $this->phase1_loop(
                            $line_cnt,
                            $directive->variableName()
                        );
                        $cut_out = true;
                        break;
                    case Directive::ENDLOOP:
                        $this->phase1_endloop(
                            $line_cnt,
                            $directive->variableName()
                        );
                        $cut_out = true;
                        break;
                    case Directive::IFDEF:
                        $this->phase1_ifdef(
                            $line_cnt,
                            $directive->variableName()
                        );
                        $cut_out = true;
                        break;
                    case Directive::IFNDEF:
                        $this->phase1_ifndef(
                            $line_cnt,
                            $directive->variableName()
                        );
                        $cut_out = true;
                        break;
                    case Directive::IFLDEF:
                        $this->phase1_ifldef(
                            $line_cnt,
                            $directive->variableName()
                        );
                        $cut_out = true;
                        break;
                    case Directive::IFLNDEF:
                        $this->phase1_iflndef(
                            $line_cnt,
                            $directive->variableName()
                        );
                        $cut_out = true;
                        break;
                    case Directive::ELSE_:
                        $this->phase1_else($line_cnt);
                        $cut_out = true;
                        break;
                    case Directive::ENDIF:
                        $this->phase1_endif($line_cnt);
                        $cut_out = true;
                        break;
                    case Directive::ENDIFL:
                        $this->phase1_endifl($line_cnt);
                        $cut_out = true;
                        break;
                }
            }
            $this->appendPhase1Line(
                $line_cnt,
                $line,
                $cut_out
            );
        }
        $this->validateConditionStack();
    }

	function fileedit($fbuf) {
		return ($fbuf);
	}
	// HTMLソースの解析 - loop
    private function phase1_loop(int $line_cnt,string $lpname): void 
    {
        $nested = count($this->arHtmlLoopStack);

        foreach ($this->arHtmlLoopStack as $frame) {
            $this->runloop[self::RUN_STACK_SEARCH]++;
            if ($frame->name() === $lpname) {
                $this->stopflag = true;
                echo "<b>html template error("
                    . $line_cnt
                    . "): tmpl:loop %"
                    . $lpname
                    . "% conflict loop name.</b><br>\n";

                return;
            }
        }

        if ($nested === 0) {
            $rootName = $lpname;
            // LoopInstance側と合わせる
            $parentName = $lpname;
        } else {
            $rootName =
                $this->arHtmlLoopStack[0]->name();
            $parentName =
                $this->arHtmlLoopStack[$nested - 1]->name();
        }

        $block = new HtmlLoopBlock(
            $lpname,
            $rootName,
            $parentName,
            $line_cnt,
            $nested + 1
        );

        $blockIndex = count($this->arHtmlLoopList);
        $this->arHtmlLoopList[] = $block;
        $this->arHtmlLoopStack[] =
            new HtmlLoopFrame(
                $block,
                $blockIndex
            );
    }



	// HTMLソースの解析 - endloop

	private function phase1_endloop(int $line_cnt,string $lpname): void 
    {
        $nested = count($this->arHtmlLoopStack);

		if ( $nested < 1 ) {
			// loop スタックが無い
			$this->stopflag = true ;
			echo "<b>html template error(" .$line_cnt. "): tmpl:endloop %" . $lpname . "% no loop-stack.</b><br>\n" ;
			return ;
		}
        $frame = $this->arHtmlLoopStack[$nested - 1];
        if ($frame->block()->name() != $lpname ) {
			// 対象 loop 名が loop スタックと一致しない
			$this->stopflag = true ;
			echo "<b>html template error(" .$line_cnt. "): tmpl:endloop %" . $lpname . "% last loop-stack name false.</b><br>\n" ;
			return ;
		}

		// 正しいループと判// このloopの終了行を記録
        $frame->block()->close($line_cnt);

        // HTML解析用loopスタックから取り除く
        array_pop($this->arHtmlLoopStack);

	}


	// HTMLソースの解析 - ifdef

	private function phase1_ifdef( $line_cnt , $name ): void 
    {
		$key = self::SMK . $name . self::SMK;

			// ネストしている
		foreach ($this->conditionStack as $frame) {
    		$this->runloop[self::RUN_STACK_SEARCH]++;
    		if ($frame->variableName() === $key) {
        		$this->stopflag = true;
		        echo "<b>html template error("
           			. $line_cnt
           			. "): tmpl:ifdef "
           			. $key
           			. " conflict name.</b><br>\n";
        		return;
    		}
		}

		$this->beginCondition(
    		Directive::IFDEF,
    		$key,
    		$line_cnt
		);
	}


	// HTMLソースの解析 - ifndef

	private function phase1_ifndef( $line_cnt , $name ): void 
    {
		$key = self::SMK . $name . self::SMK;

		// ネストしている
		foreach ($this->conditionStack as $frame) {
    		$this->runloop[self::RUN_STACK_SEARCH]++;
    		if ($frame->variableName() === $key) {
				// ネスト中の ifdef が重複
				$this->stopflag = true ;
			    echo "<b>html template error("
            		. $line_cnt
            		. "): tmpl:ifndef "
            		. $key
            		. " conflict name.</b><br>\n";
				return ;
			}
		}
		$this->beginCondition(
    		Directive::IFNDEF,
    		$key,
    		$line_cnt
		);

	}
    // HTMLソースの解析 - ifldef
    private function phase1_ifldef(int $line_cnt,string $name): void
    {
        $key = self::SMK . $name . self::SMK;

        $this->beginCondition(
            Directive::IFLDEF,
            $key,
            $line_cnt
        );
    }
    private function phase1_iflndef(int $line_cnt,string $name): void
    {
        $key = self::SMK . $name . self::SMK;

        $this->beginCondition(
            Directive::IFLNDEF,
            $key,
            $line_cnt
        );
    }    
	// HTMLソースの解析 - else
	private function phase1_else(int $line_cnt): void
	{
    	if ($this->conditionStack === []) {
        	$this->stopflag = true;

        	echo "<b>html template error("
            	. $line_cnt
            	. "): tmpl:else without condition.</b><br>\n";

        	return;
    	}

    	$frame = array_pop($this->conditionStack);
    	$block = $frame->block();

        if ($block->isElseBranch()) {
            $this->stopflag = true;
            echo "<b>html template error("
                . $line_cnt
                . "): duplicate tmpl:else.</b><br>\n";
                // popしてしまったので戻しておく
            $this->conditionStack[] = $frame;
            return;
        }

        $block->close($line_cnt);

        $newType = Directive::reverse(
            $block->type()
        );

        $this->beginCondition(
            $newType,
            $block->variableName(),
            $line_cnt,
            true
        );
	}
	// HTMLソースの解析 - endif
	private function phase1_endif(int $line_cnt): void
	{
    	if ($this->conditionStack === []) {
        	$this->stopflag = true;

        	echo "<b>html template error("
            	. $line_cnt
            	. "): tmpl:endif without ifdef/ifndef.</b><br>\n";
	        return;
    	}
    	$frame = $this->currentConditionFrame();
    	if ($frame->block()->isLocalCondition()) {
	        $this->stopflag = true;
	        echo "<b>html template error("
    	        . $line_cnt
        	    . "): tmpl:endif cannot close "
            	. $frame->block()->type()
            	. ". use tmpl:endifl.</b><br>\n";
	        return;
    	}
    	$this->endCondition($line_cnt);
	}
	// HTMLソースの解析 - endifl
	private function phase1_endifl(int $line_cnt): void
	{
    	if ($this->conditionStack === []) {
        	$this->stopflag = true;
	        echo "<b>html template error("
    	        . $line_cnt
        	    . "): tmpl:endifl without ifldef/iflndef.</b><br>\n";
	        return;
    	}
	    $frame = $this->currentConditionFrame();
    	if (!$frame->block()->isLocalCondition()) {
	        $this->stopflag = true;
	        echo "<b>html template error("
    	        . $line_cnt
        	    . "): tmpl:endifl cannot close "
            	. $frame->block()->type()
            	. ".</b><br>\n";
	        return;
    	}
	    $this->endCondition($line_cnt);
	}
	// HTMLソースの解析 - def

	function phase1_def( $line_cnt , $name ) {
		$key = self::SMK . $name . self::SMK;
		$this->arDefValue[ $key ] = true ; 

		if ( $this->dbg > 1 ) {
			echo "<!-- HTML(" .$line_cnt. "): tmpl:def " . $key . " assign -->\n" ;
		}
	}





	// ifdef / ifndef の解析
	private function phase2(): void
	{
    	$del_nested = -1;  // 1以上なら、そのネストを破棄
    	$del_type   = '';
    	$del_self   = '';

    	if ($this->conditionBlocks === []) {
        	// HTML上に ifdef / ifndef が存在しないなら
        	$this->arHtmlTemp2 = $this->arHtmlTemp;
        	return;
    	}

    	foreach ($this->arHtmlTemp as $key => $work) {
        	$this->runloop[self::RUN_PHASE2]++;

        	$def_list = $work->conditionBlockIndex();

        	if ($def_list === -1) {
            	// ifdef / ifndef ブロックではない
            	$this->arHtmlTemp2[$key] = $work;
            	continue;
        	}

        	$block = $this->conditionBlocks[$def_list];

        	$def_nest = $block->depth();
        	$def_self = $block->variableName();
        	$def_type = $block->type();

        	if ($del_nested === -1) {
            	if (($def_type === 'ifdef')
                     && !isset($this->arDefValue[$def_self])
            	) {
                	$del_nested = $def_nest;
                	$del_type   = $def_type;
                	$del_self   = $def_self;
                	continue;
            	}

            	if (
                	($def_type === 'ifndef')
                	&& isset($this->arDefValue[$def_self])
            	) {
                	$del_nested = $def_nest;
                	$del_type   = $def_type;
                	$del_self   = $def_self;
                	continue;
            	}
        	} else {
            	if ($def_nest > $del_nested) {
                	// ネストの深いブロックも破棄
                	continue;
            	}

            	if ($def_nest === $del_nested) {
                	// ネストの深さは同じだが、条件変化をチェック
                	if (
                    	($def_self === $del_self)
                    	&& ($def_type === $del_type)
                	) {
                    // 条件変化無し
                    	continue;
                	}
                	if (
                    	($def_type === 'ifdef')
                    	&& !isset($this->arDefValue[$def_self])
                	) {
                    	$del_nested = $def_nest;
                    	$del_type   = $def_type;
                    	$del_self   = $def_self;
                    	continue;
                	}

                	if (
                    	($def_type === 'ifndef')
                    	&& isset($this->arDefValue[$def_self])
                	) {
                    	$del_nested = $def_nest;
                    	$del_type   = $def_type;
                    	$del_self   = $def_self;
                    	continue;
                	}
            	}

            	if ($def_nest < $del_nested) {
                	// ネストの深さが変化したので、再度判断
                	if (
                    	($def_type === 'ifdef')
                    	&& !isset($this->arDefValue[$def_self])
                	) {
                    	$del_nested = $def_nest;
                    	$del_type   = $def_type;
                    	$del_self   = $def_self;
                    	continue;
                	}

                	if (
                    	($def_type === 'ifndef')
                    	&& isset($this->arDefValue[$def_self])
                	) {
                    	$del_nested = $def_nest;
                    	$del_type   = $def_type;
                    	$del_self   = $def_self;
                    	continue;
                	}
            	}

            	$del_nested = -1;
            	$del_type   = '';
            	$del_self   = '';
        	}

        	$this->arHtmlTemp2[$key] = $work;
    	}
	}
	// loop の展開、置換処理
    private function phase3(): void
    {
        $skip = -1;  // スキップする行
        foreach ($this->arHtmlTemp2 as $key => $work) {
            $this->runloop[self::RUN_PHASE3]++;

            if ($key < $skip) {
                continue;
            }

            $skip = -1;

            $html_list = $work->htmlLoopBlockIndex();
            $lop_nest = 0;
            if ($html_list !== -1) {
                $lop_nest =
                    $this->arHtmlLoopList[$html_list]->depth();
            }
            if ($lop_nest === 0) {
                if ($work->isCutOut()) {
                    // 出力対象でない
                    continue;
                }
                if ($work->convertCount() === 0) {
                    // 置換無し
                    echo $work->source();
                    continue;
                }
                // 置換有り
                echo $this->phase3_convert(
                    $work->source()
                );
                continue;
            }
            // loop処理
            $skip = $this->phase3_loop($key, 1);
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
					if (self::UNDEF2BLANK){
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
    private function phase3_loop(int $key, int $pcnt): int
    {        
        $html_list  = -1;
        $php_list   = -1;
        $html_start = -1;
        $html_end   = -1;
        $lop_cnt    = -1;

        $html_list = $this->arHtmlTemp2[$key]->htmlLoopBlockIndex();

        $block = $this->arHtmlLoopList[$html_list];

        $lop_root   = $block->rootName();
        $lop_parent = $block->parentName();
        $lop_self   = $block->name();
        $lop_nest   = $block->depth();
        $html_start = $block->startLine();
        $html_end   = $block->endLine();


        // PHP側 loop リスト検索
        $phpInstance = null;
        foreach ($this->arPhpLoopList as $key3 => $instance) {
            $this->runloop[self::RUN_LOOP_SEARCH]++;
            if (
                $instance->rootName() === $lop_root
                && $instance->parentName() === $lop_parent
                && $instance->parentRowNumber() === $pcnt
                && $instance->name() === $lop_self
                && $instance->depth() === $lop_nest
            ) {
                $php_list = $key3;
                $phpInstance = $instance;
                $lop_allcnt = $instance->rowCount();
                unset($this->arPhpLoopList[$key3]);
                if (DEF_DEBUG) {
                    echo '<pre>';
                    echo "HTML LOOP\n";
                    var_dump(
                        $lop_root,
                        $lop_parent,
                        $lop_self,
                        $lop_nest,
                        $pcnt
                    );

                    foreach ($this->arPhpLoopList as $instance) {
                        echo "PHP LOOP\n";
                        var_dump(
                            $instance->rootName(),
                            $instance->parentName(),
                            $instance->name(),
                            $instance->depth(),
                            $instance->parentRowNumber()
                        );
                    }
                    echo '</pre>';
                }
                break;
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
            $convert = [];
            foreach ($this->arLoopValue as $key4 => $row) {
                $this->runloop[self::RUN_VALUE_SEARCH]++;
                if ($row->instance() === $phpInstance && $row->rowNumber() === $plop) {
                    $convert = $row->values();
                    $cnv_cnt++;
                    unset($this->arLoopValue[$key4]);
                    break;
                }
            }

            // HTML側 ループ数
            $skip = -1 ;  // スキップする行
            $out_flag = 0 ;  // 出力フラグ（ifldef用）
            for( $hlop = ( $html_start - 1 ) ; $hlop < $html_end ; $hlop ++ ) {
                $this->runloop[self::RUN_HTML_LOOP] ++ ;
                if ( $hlop < $skip ) {
                    continue ;
                }
                $skip = -1 ;

                if ( ! isset( $this->arHtmlTemp2[ $hlop ] ) ) {
                    // ifdef / ifndef ブロックで除去済み
                    continue ;
                }
                $work = $this->arHtmlTemp2[$hlop];
                $lbuf = $work->source();

                $conditionIndex = $work->conditionBlockIndex();
                $localCondition = false;

                if ($conditionIndex !== -1) {
                    $conditionBlock =
                        $this->conditionBlocks[$conditionIndex];
                    $conditionType = $conditionBlock->type();
                    $localCondition =
                        $conditionBlock->isLocalCondition();
                }
                if ($out_flag === 0) {
                    // ifldef
                    if (preg_match(
                        '~<!--[ \t]+tmpl:ifldef[ \t]+(%.*?%)[ \t].*-->~i',
                        $lbuf,
                        $regs)
                    ) {
                        if (!isset($convert[$regs[1]])) {
                            $out_flag = 1;
                        }
                        continue;
                    }

                    // iflndef
                    if (preg_match(
                        '~<!--[ \t]+tmpl:iflndef[ \t]+(%.*?%)[ \t].*-->~i',
                        $lbuf,
                        $regs)
                    ) {
                        if (isset($convert[$regs[1]])) {
                            $out_flag = 1;
                        }
                        continue;
                    }
                    // else
                    if ($localCondition && 
                        preg_match(
                            '~<!--[ \t]+tmpl:else[ \t]*-->~i',
                            $lbuf)
                    ) {
                        $out_flag = 1;
                        continue;
                    }

                } else {
                    // else
                    if ($localCondition &&
                        preg_match(
                        '~<!--[ \t]+tmpl:else[ \t]*-->~i',
                        $lbuf)
                    ) {
                        // 前半が非表示だったなら、
                        // else側を表示
                        $out_flag = 0;
                        continue;
                    }
                    // endifl
                    if (preg_match(
                        '~<!--[ \t]+tmpl:endifl[ \t].*-->~i',
                        $lbuf)
                    ) {
                        $out_flag = 0;
                        continue;
                    }
                }

                if ($out_flag) {
                    continue;
                }
                $loopIndex = $work->htmlLoopBlockIndex();
                $lop_nest2 = 0;
                if ($loopIndex !== -1) {
                    $lop_nest2 =
                        $this->arHtmlLoopList[$loopIndex]->depth();
                }

                if ($lop_nest2 > $lop_nest) {
                    $skip = $this->phase3_loop($hlop,$plop);
                    continue;
                }

                if ($work->isCutOut()) {
                    continue;
                }

                if ($work->convertCount() === 0) {
                    echo $work->source();
                } else {
                    echo $this->phase3_loopconvert(
                        $work->source(),
                        $convert);
                }
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
					if (self::UNDEF2BLANK){
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
			return( $contents ) ;
		} else {
			echo $contents ;
		}
	}


}
// endof Tmpl-class
?>